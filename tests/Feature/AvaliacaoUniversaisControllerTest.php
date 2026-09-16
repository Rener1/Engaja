<?php

namespace Tests\Feature;

use App\Models\Avaliacao;
use App\Models\AvaliacaoQuestao;
use App\Models\TemplateAvaliacao;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use SimpleSoftwareIO\QrCode\Generator;
use Tests\TestCase;

class AvaliacaoUniversaisControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
        config()->set('cartas.url', 'https://cartas.example.test');
    }

    public function test_index_renderiza_grid_com_dados(): void
    {
        // Evita depender da extensão imagick (usada pelo backend PNG do
        // simple-qrcode) só para este teste de renderização da tabela.
        $qrCodeMock = Mockery::mock(Generator::class);
        $qrCodeMock->shouldReceive('format', 'style', 'color', 'eye', 'eyeColor', 'size', 'margin', 'merge', 'errorCorrection')
            ->andReturnSelf();
        $qrCodeMock->shouldReceive('generate')->andReturn('');
        $this->app->instance(Generator::class, $qrCodeMock);

        $user = User::factory()->create();
        $user->assignRole('administrador');

        $template = TemplateAvaliacao::factory()->create(['nome' => 'Modelo Universal']);
        Avaliacao::factory()->create([
            'atividade_id' => null,
            'template_avaliacao_id' => $template->id,
            'descricao_universal' => 'Avaliação de satisfação geral',
        ]);

        $this->actingAs($user)
            ->get(route('avaliacoes-universais.index'))
            ->assertOk()
            ->assertSee('grid-avaliacoes-universais', false)
            ->assertSee('Avaliação de satisfação geral')
            ->assertSee('Modelo Universal');
    }

    public function test_criacao_persiste_flag_do_cartas_sem_alterar_universal_comum(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');
        $template = TemplateAvaliacao::factory()->create();

        $this->actingAs($user)
            ->post(route('avaliacoes-universais.store'), [
                'template_avaliacao_id' => $template->id,
                'descricao_universal' => 'Pesquisa do Cartas',
                'is_cartas' => '1',
            ])
            ->assertRedirect(route('avaliacoes-universais.index'));

        $this->actingAs($user)
            ->post(route('avaliacoes-universais.store'), [
                'template_avaliacao_id' => $template->id,
                'descricao_universal' => 'Pesquisa do Engaja',
            ])
            ->assertRedirect(route('avaliacoes-universais.index'));

        $this->assertDatabaseHas('avaliacaos', [
            'descricao_universal' => 'Pesquisa do Cartas',
            'is_cartas' => true,
        ]);
        $this->assertDatabaseHas('avaliacaos', [
            'descricao_universal' => 'Pesquisa do Engaja',
            'is_cartas' => false,
        ]);
    }

    public function test_gerenciar_gera_link_do_cartas_apenas_para_avaliacao_marcada(): void
    {
        $qrCodeMock = Mockery::mock(Generator::class);
        $qrCodeMock->shouldReceive('format', 'style', 'color', 'eye', 'eyeColor', 'size', 'margin', 'merge', 'errorCorrection')
            ->andReturnSelf();
        $qrCodeMock->shouldReceive('generate')->twice()->andReturn('');
        $this->app->instance(Generator::class, $qrCodeMock);

        $user = User::factory()->create();
        $user->assignRole('administrador');
        $template = TemplateAvaliacao::factory()->create();
        $cartas = Avaliacao::factory()->create([
            'atividade_id' => null,
            'template_avaliacao_id' => $template->id,
            'is_cartas' => true,
        ]);
        $engaja = Avaliacao::factory()->create([
            'atividade_id' => null,
            'template_avaliacao_id' => $template->id,
            'is_cartas' => false,
        ]);

        $this->actingAs($user)
            ->get(route('avaliacoes-universais.index'))
            ->assertOk()
            ->assertSee("https://cartas.example.test/cartas/formulario-avaliacao/{$cartas->id}", false)
            ->assertSee(route('avaliacao.formulario', $engaja), false);
    }

    public function test_rotas_publicas_isolam_avaliacoes_do_cartas_e_do_engaja(): void
    {
        $template = TemplateAvaliacao::factory()->create();
        $cartas = Avaliacao::factory()->create([
            'atividade_id' => null,
            'template_avaliacao_id' => $template->id,
            'is_cartas' => true,
        ]);
        $engaja = Avaliacao::factory()->create([
            'atividade_id' => null,
            'template_avaliacao_id' => $template->id,
            'is_cartas' => false,
        ]);

        $this->get(route('cartas.avaliacao.formulario', $cartas))
            ->assertOk()
            ->assertSee("https://cartas.example.test/cartas/formulario-avaliacao/{$cartas->id}", false);
        $this->get(route('avaliacao.formulario', $cartas))->assertNotFound();

        $this->get(route('avaliacao.formulario', $engaja))->assertOk();
        $this->get(route('cartas.avaliacao.formulario', $engaja))->assertNotFound();
    }

    public function test_resposta_do_cartas_e_salva_e_redirecionada_no_dominio_correto(): void
    {
        $avaliacao = Avaliacao::factory()->create([
            'atividade_id' => null,
            'is_cartas' => true,
            'formulario_aberto' => true,
        ]);
        $questao = AvaliacaoQuestao::create([
            'avaliacao_id' => $avaliacao->id,
            'texto' => 'Como foi sua experiência?',
            'tipo' => 'texto',
            'ordem' => 1,
            'fixa' => false,
        ]);

        $this->post(route('cartas.avaliacao.formulario.responder', $avaliacao), [
            'respostas' => [$questao->id => 'Foi muito boa.'],
        ])->assertRedirect("https://cartas.example.test/cartas/formulario-avaliacao/{$avaliacao->id}/obrigado");

        $this->assertDatabaseHas('submissao_avaliacoes', [
            'avaliacao_id' => $avaliacao->id,
            'atividade_id' => null,
            'universal' => true,
        ]);
        $this->assertDatabaseHas('resposta_avaliacaos', [
            'avaliacao_id' => $avaliacao->id,
            'avaliacao_questao_id' => $questao->id,
            'resposta' => 'Foi muito boa.',
        ]);
    }

    public function test_flag_do_cartas_nao_pode_ser_alterada_na_edicao(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');
        $avaliacao = Avaliacao::factory()->create([
            'atividade_id' => null,
            'is_cartas' => true,
        ]);

        $this->actingAs($user)
            ->put(route('avaliacoes-universais.update', $avaliacao), [
                'template_avaliacao_id' => $avaliacao->template_avaliacao_id,
                'descricao_universal' => $avaliacao->descricao_universal,
                'is_cartas' => '0',
            ])
            ->assertSessionHasErrors('is_cartas');

        $this->assertTrue($avaliacao->fresh()->is_cartas);
    }
}
