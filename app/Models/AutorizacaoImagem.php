<?php

namespace App\Models;

use App\Enums\SituacaoAutorizacaoImagem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutorizacaoImagem extends Model
{
    protected $table = 'autorizacoes_imagem';

    protected $fillable = [
        'participante_id', 'evento_id', 'autorizado', 'respondido_em',
        'origem', 'registrado_por_user_id', 'presenca_origem_id', 'acao_nome', 'versao',
    ];

    protected function casts(): array
    {
        return ['autorizado' => 'boolean', 'respondido_em' => 'datetime', 'versao' => 'integer'];
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function historico(): HasMany
    {
        return $this->hasMany(AutorizacaoImagemHistorico::class)->orderByDesc('id');
    }

    public function situacao(): SituacaoAutorizacaoImagem
    {
        return SituacaoAutorizacaoImagem::daResposta($this);
    }
}
