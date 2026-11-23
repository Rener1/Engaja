<?php

namespace App\Models\Cartas;

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Municipio;
use App\Models\Participante;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Carta extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_AGUARDANDO_DISTRIBUICAO = 'aguardando_distribuicao';

    public const STATUS_AGUARDANDO_VOLUNTARIO = 'aguardando_voluntario';

    public const STATUS_AGUARDANDO_VERIFICACAO = 'aguardando_verificacao';

    public const STATUS_AGUARDANDO_AJUSTE = 'aguardando_ajuste';

    public const STATUS_AGUARDANDO_EDUCANDO = 'aguardando_educando';

    public const STATUS_RESPONDIDA = 'respondida';

    public const STATUS_ENCERRADA = 'encerrada';

    public const STATUSES = [
        self::STATUS_RASCUNHO,
        self::STATUS_AGUARDANDO_DISTRIBUICAO,
        self::STATUS_AGUARDANDO_VOLUNTARIO,
        self::STATUS_AGUARDANDO_VERIFICACAO,
        self::STATUS_AGUARDANDO_AJUSTE,
        self::STATUS_AGUARDANDO_EDUCANDO,
        self::STATUS_RESPONDIDA,
        self::STATUS_ENCERRADA,
    ];

    protected $fillable = [
        'codigo',
        'envio_token',
        'envio_hash',
        'evento_id',
        'atividade_id',
        'educando_participante_id',
        'voluntario_user_id',
        'municipio_id',
        'turma',
        'status',
        'distribuida_em',
        'encerrada_em',
        'criada_por',
        'atualizada_por',
    ];

    protected function casts(): array
    {
        return [
            'distribuida_em' => 'datetime',
            'encerrada_em' => 'datetime',
        ];
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }

    public function educando(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'educando_participante_id');
    }

    public function voluntario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voluntario_user_id');
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    public function criadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criada_por');
    }

    public function atualizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizada_por');
    }

    public function mensagens(): HasMany
    {
        return $this->hasMany(CartaMensagem::class)->orderBy('rodada');
    }

    public function ultimaMensagem(): HasOne
    {
        return $this->hasOne(CartaMensagem::class)->latestOfMany();
    }

    public function eventosAuditoria(): HasMany
    {
        return $this->hasMany(CartaEvento::class)->latest('created_at');
    }

    public function podeAprovarResposta(): bool
    {
        $mensagem = $this->ultimaMensagem;

        return ! in_array($this->status, [
            self::STATUS_RESPONDIDA,
            self::STATUS_AGUARDANDO_AJUSTE,
            self::STATUS_ENCERRADA,
        ], true)
            && $mensagem?->status === CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO
            && $mensagem->tipo_remetente === CartaMensagem::TIPO_REMETENTE_VOLUNTARIO
            && $mensagem->enviada_em !== null;
    }

    /**
     * Uma mensagem ainda aguardando verificação ou ajuste bloqueia novos envios na conversa.
     */
    public function temMensagemPendente(): bool
    {
        return in_array($this->ultimaMensagem?->status, [
            CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO,
            CartaMensagem::STATUS_AJUSTE_SOLICITADO,
        ], true);
    }

    /**
     * Lado que deve enviar a próxima mensagem, alternando educando/voluntário.
     * Retorna null quando há mensagem pendente (ninguém pode enviar).
     */
    public function proximoTipoRemetente(): ?string
    {
        if ($this->temMensagemPendente()) {
            return null;
        }

        $ultima = $this->ultimaMensagem;

        if (! $ultima) {
            return CartaMensagem::TIPO_REMETENTE_EDUCANDO;
        }

        return $ultima->tipo_remetente === CartaMensagem::TIPO_REMETENTE_EDUCANDO
            ? CartaMensagem::TIPO_REMETENTE_VOLUNTARIO
            : CartaMensagem::TIPO_REMETENTE_EDUCANDO;
    }

    public function podeEducandoEnviar(): bool
    {
        return $this->proximoTipoRemetente() === CartaMensagem::TIPO_REMETENTE_EDUCANDO;
    }

    public function podeVoluntarioEnviar(): bool
    {
        return $this->proximoTipoRemetente() === CartaMensagem::TIPO_REMETENTE_VOLUNTARIO;
    }

    public function scopeDoVoluntario(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->where('voluntario_user_id', $userId);
    }

    public function scopeAguardandoVerificacao(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AGUARDANDO_VERIFICACAO);
    }
}
