<?php

namespace Database\Factories;

use App\Models\Atividade;
use App\Models\Avaliacao;
use App\Models\TemplateAvaliacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Avaliacao>
 */
class AvaliacaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_avaliacao_id' => TemplateAvaliacao::factory(),
            'atividade_id' => Atividade::factory(),
            'descricao_universal' => fake()->sentence(4),
            'anonima' => true,
            'is_cartas' => false,
        ];
    }
}
