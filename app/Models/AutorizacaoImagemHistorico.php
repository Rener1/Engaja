<?php

namespace App\Models;

use App\Models\Concerns\RegistroImutavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutorizacaoImagemHistorico extends Model
{
    use RegistroImutavel;

    public const UPDATED_AT = null;

    protected $table = 'historicos_autorizacao_imagem';

    protected $fillable = [
        'autorizacao_imagem_id', 'autorizado_anterior', 'autorizado', 'respondido_em',
        'origem', 'registrado_por_user_id', 'presenca_origem_id', 'acao_nome',
    ];

    protected function casts(): array
    {
        return ['autorizado_anterior' => 'boolean', 'autorizado' => 'boolean', 'respondido_em' => 'datetime'];
    }

    public function autorizacaoImagem(): BelongsTo
    {
        return $this->belongsTo(AutorizacaoImagem::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }
}
