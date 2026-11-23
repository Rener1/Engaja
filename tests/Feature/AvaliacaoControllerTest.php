<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\Avaliacao;
use App\Models\Evento;
use App\Models\TemplateAvaliacao;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvaliacaoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_index_renderiza_grid_com_dados(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        $atividade = Atividade::factory()->create([
            'evento_id' => Evento::factory(),
            'descricao' => 'Encontro de abertura',
        ]);
        Avaliacao::factory()->create(['atividade_id' => $atividade->id]);

        $this->actingAs($user)
            ->get(route('avaliacoes.index'))
            ->assertOk()
            ->assertSee('grid-avaliacoes', false)
            ->assertSee('Encontro de abertura');
    }

    public function test_criacao_exibe_componente_apenas_para_avaliacoes_com_momento(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');
        $atividade = Atividade::factory()->create([
            'evento_id' => Evento::factory(),
            'descricao' => 'Momento para avaliar',
            'dia' => '2026-10-28',
        ]);

        $this->actingAs($user)
            ->get(route('avaliacoes.create'))
            ->assertOk()
            ->assertSee('data-momento-select', false)
            ->assertSee('data-date="2026-10-28"', false)
            ->assertSee('Momento para avaliar');

        $this->get(route('avaliacoes-universais.create'))
            ->assertOk()
            ->assertDontSee('data-momento-select', false);
    }

    public function test_criacao_salva_momento_selecionado(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');
        $atividade = Atividade::factory()->create(['evento_id' => Evento::factory()]);
        $template = TemplateAvaliacao::factory()->create();

        $this->actingAs($user)
            ->post(route('avaliacoes.store'), [
                'atividade_id' => (string) $atividade->id,
                'template_avaliacao_id' => $template->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('avaliacoes.index'));

        $this->assertDatabaseHas('avaliacaos', [
            'atividade_id' => $atividade->id,
            'template_avaliacao_id' => $template->id,
        ]);
    }

    public function test_criacao_recupera_momento_quando_outro_campo_falha(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');
        $atividade = Atividade::factory()->create(['evento_id' => Evento::factory()]);
        $template = TemplateAvaliacao::factory()->create();

        $this->actingAs($user)
            ->from(route('avaliacoes.create'))
            ->post(route('avaliacoes.store'), [
                'atividade_id' => $atividade->id,
                'template_avaliacao_id' => $template->id,
                'descricao_universal' => str_repeat('a', 256),
            ])
            ->assertRedirect(route('avaliacoes.create'))
            ->assertSessionHasErrors('descricao_universal');

        $response = $this->get(route('avaliacoes.create'))->assertOk();

        $this->assertMatchesRegularExpression(
            '/<option value="'.preg_quote((string) $atividade->id, '/').'"[^>]*\bselected\b/u',
            $response->getContent()
        );
    }

    public function test_criacao_rejeita_momento_invalido_ou_excluido(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');
        $atividade = Atividade::factory()->create(['evento_id' => Evento::factory()]);
        $template = TemplateAvaliacao::factory()->create();

        $this->actingAs($user);
        foreach ([null, 'invalido', [$atividade->id], $atividade->id + 1000] as $value) {
            $this->post(route('avaliacoes.store'), [
                'atividade_id' => $value,
                'template_avaliacao_id' => $template->id,
            ])->assertSessionHasErrors('atividade_id');
        }

        $atividade->delete();
        $this->post(route('avaliacoes.store'), [
            'atividade_id' => $atividade->id,
            'template_avaliacao_id' => $template->id,
        ])->assertSessionHasErrors('atividade_id');

        $this->assertDatabaseCount('avaliacaos', 0);
    }

    public function test_participante_nao_pode_acessar_criacao_de_avaliacoes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('participante');

        $this->actingAs($user)
            ->get(route('avaliacoes.create'))
            ->assertForbidden();
    }
}
