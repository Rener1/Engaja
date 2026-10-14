<?php

namespace App\Models\Cartas;

use App\Models\Participante;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CartaMensagem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'carta_mensagens';

    public const TIPO_REMETENTE_EDUCANDO = 'educando';

    public const TIPO_REMETENTE_VOLUNTARIO = 'voluntario';

    public const TIPO_REMETENTE_SISTEMA = 'sistema';

    public const CANAL_DIGITADA = 'digitada';

    public const CANAL_ANEXO_MANUSCRITO = 'anexo_manuscrito';

    public const CANAL_ANEXO_DIGITALIZADO = 'anexo_digitalizado';

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_AGUARDANDO_VERIFICACAO = 'aguardando_verificacao';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_AJUSTE_SOLICITADO = 'ajuste_solicitado';

    public const STATUS_CANCELADA = 'cancelada';

    public const STATUSES = [
        self::STATUS_RASCUNHO,
        self::STATUS_AGUARDANDO_VERIFICACAO,
        self::STATUS_APROVADA,
        self::STATUS_AJUSTE_SOLICITADO,
        self::STATUS_CANCELADA,
    ];

    protected $fillable = [
        'carta_id',
        'rodada',
        'remetente_user_id',
        'remetente_participante_id',
        'destinatario_user_id',
        'destinatario_participante_id',
        'tipo_remetente',
        'canal_entrada',
        'status',
        'texto',
        'texto_resumo',
        'anexo_original_path',
        'anexo_original_nome',
        'anexo_original_mime',
        'anexo_original_tamanho',
        'arquivo_final_path',
        'arquivo_final_nome',
        'arquivo_final_mime',
        'arquivo_final_tamanho',
        'timbrado_aplicado_em',
        'enviada_em',
        'lida_em',
        'verificada_por',
        'verificada_em',
        'parecer_verificacao',
        'criada_por',
        'atualizada_por',
    ];

    protected function casts(): array
    {
        return [
            'timbrado_aplicado_em' => 'datetime',
            'enviada_em' => 'datetime',
            'lida_em' => 'datetime',
            'verificada_em' => 'datetime',
        ];
    }

    protected $touches = ['carta'];

    public function carta(): BelongsTo
    {
        return $this->belongsTo(Carta::class);
    }

    public function remetenteUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remetente_user_id');
    }

    public function remetenteParticipante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'remetente_participante_id');
    }

    public function destinatarioUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destinatario_user_id');
    }

    public function destinatarioParticipante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'destinatario_participante_id');
    }

    public function verificadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificada_por');
    }

    public function criadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criada_por');
    }

    public function atualizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizada_por');
    }

    public function isRascunho(): bool
    {
        return $this->status === self::STATUS_RASCUNHO;
    }

    public function isAguardandoVerificacao(): bool
    {
        return $this->status === self::STATUS_AGUARDANDO_VERIFICACAO;
    }

    public function isAprovada(): bool
    {
        return $this->status === self::STATUS_APROVADA;
    }

    public function isEditavelPor(User $user): bool
    {
        if ($user->can('cartas.editar-enviada')) {
            return true;
        }

        return in_array($this->status, [self::STATUS_RASCUNHO, self::STATUS_AJUSTE_SOLICITADO], true)
            && in_array($user->id, [$this->remetente_user_id, $this->criada_por], true);
    }
}
