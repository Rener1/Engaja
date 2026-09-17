<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Avaliacao extends Model
{
    use HasFactory;

    protected $fillable = ['template_avaliacao_id', 'descricao_universal', 'formulario_aberto', 'is_cartas', 'inscricao_id', 'atividade_id', 'anonima', 'transcricao'];

    protected $casts = [
        'anonima' => 'boolean',
        'formulario_aberto' => 'boolean',
        'is_cartas' => 'boolean',
        'transcricao' => 'boolean',
    ];

    public function getAnonimaAttribute($value): bool
    {
        // Tratamos null como anônima para retrocompatibilidade
        return $value === null ? true : (bool) $value;
    }

    public function templateAvaliacao(): BelongsTo
    {
        return $this->belongsTo(TemplateAvaliacao::class);
    }

    public function inscricao(): BelongsTo
    {
        return $this->belongsTo(Inscricao::class);
    }

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }

    public function respostas(): HasMany
    {
        return $this->hasMany(RespostaAvaliacao::class);
    }

    public function avaliacaoQuestoes(): HasMany
    {
        return $this->hasMany(AvaliacaoQuestao::class)
            ->orderBy('ordem')
            ->orderBy('id');
    }
}
