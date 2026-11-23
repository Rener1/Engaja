<?php

namespace Tests\Feature\Cartas;

use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaMensagem;
use App\Models\User;
use App\Services\Cartas\RelatorioCartasService;
use Database\Seeders\CartasRelatoriosPermissionsSeeder;
use Spatie\Permission\Models\Role;

class CartasRelatoriosTest extends CartasBaseTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_gestor_acessa_relatorios_e_menu_com_dados_vazios(): void
    {
        $response = $this->actingAs($this->gestor)->get(route('cartas.relatorios.index'));

        $response->assertOk()
            ->assertSee('Relatórios')
            ->assertSee(route('cartas.relatorios.index'), false)
            ->assertSee('Ainda não há dados para este gráfico.')
            ->assertViewHas('metricas', [
                'voluntarios' => 2,
                'capacidade' => 20,
                'atribuidas' => 0,
                'saldo' => 20,
            ]);
        $this->assertSame([0, 0], $response->viewData('graficos')['respostas']['values']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_administrador_cartas_com_permissao_acessa_relatorios(): void
    {
        $this->seed(CartasRelatoriosPermissionsSeeder::class);

        $admin = User::factory()->create(['sistema_origem' => User::SISTEMA_CARTAS]);
        $admin->assignRole('cartas_admin');

        $this->actingAs($admin)->get(route('cartas.relatorios.index'))->assertOk();
    }

    public function test_voluntario_nao_acessa_relatorios_mesmo_com_permissao_avulsa(): void
    {
        $this->voluntario->givePermissionTo('cartas.relatorio');

        $this->actingAs($this->voluntario)->get(route('cartas.relatorios.index'))->assertForbidden();
        $this->actingAs($this->voluntario)
            ->get(route('cartas.dashboard'))
            ->assertOk()
            ->assertDontSee(route('cartas.relatorios.index'), false);
    }

    public function test_usuario_engaja_nao_acessa_relatorios_mesmo_com_perfil_cartas(): void
    {
        $this->remetente->assignRole('cartas_gestao');

        $this->actingAs($this->remetente)->get(route('cartas.relatorios.index'))->assertForbidden();
    }

    public function test_visitante_e_gestor_nao_verificado_seguem_fluxo_de_autenticacao(): void
    {
        $this->get(route('cartas.relatorios.index'))->assertRedirect(route('cartas.login'));

        $this->gestor->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->gestor)
            ->get(route('cartas.relatorios.index'))
            ->assertRedirect(route('cartas.verification.notice'));
    }

    public function test_permissao_de_relatorio_e_exigida_tambem_para_gestor(): void
    {
        Role::findByName('cartas_gestao')->revokePermissionTo('cartas.relatorio');

        $this->actingAs($this->gestor)->get(route('cartas.relatorios.index'))->assertForbidden();
    }

    public function test_primeira_resposta_conta_em_analise_e_ajuste_sem_duplicar_rodadas(): void
    {
        $this->criarTroca($this->voluntario);

        $emAnalise = $this->criarTroca($this->voluntario);
        $this->adicionarMensagem($emAnalise, ['status' => CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO]);

        $comRodadas = $this->criarTroca($this->voluntario);
        $this->adicionarMensagem($comRodadas, ['status' => CartaMensagem::STATUS_AJUSTE_SOLICITADO]);
        $this->adicionarMensagem($comRodadas, ['tipo_remetente' => CartaMensagem::TIPO_REMETENTE_EDUCANDO]);
        $this->adicionarMensagem($comRodadas);

        $cancelada = $this->criarTroca($this->voluntario);
        $this->adicionarMensagem($cancelada, ['status' => CartaMensagem::STATUS_CANCELADA]);

        $this->criarCartaComRespostaAguardandoVerificacao(); // Iniciada pelo voluntário.

        $rascunho = $this->criarTroca($this->voluntario);
        $rascunho->mensagens()->first()->update(['status' => CartaMensagem::STATUS_RASCUNHO, 'enviada_em' => null]);
        $this->criarTroca($this->voluntario)->delete();

        $resumo = app(RelatorioCartasService::class)->resumo();
        $this->assertSame(4, $resumo['graficos']['respostas']['total']);
        $this->assertSame([2, 2], $resumo['graficos']['respostas']['values']);

        $pendentes = app(RelatorioCartasService::class)->voluntariosComPendencias();
        $this->assertSame(1, $pendentes->total());
        $this->assertSame($this->voluntario->id, $pendentes->first()->id);
        $this->assertSame(2, $pendentes->first()->cartas_pendentes_count);
    }

    public function test_rascunho_resposta_nao_enviada_e_resposta_excluida_nao_contam(): void
    {
        $rascunho = $this->criarTroca($this->voluntario);
        $this->adicionarMensagem($rascunho, ['status' => CartaMensagem::STATUS_RASCUNHO]);
        $naoEnviada = $this->criarTroca($this->voluntario);
        $this->adicionarMensagem($naoEnviada, ['enviada_em' => null]);
        $excluida = $this->criarTroca($this->voluntario);
        $this->adicionarMensagem($excluida)->delete();

        $this->assertSame([0, 3], app(RelatorioCartasService::class)->resumo()['graficos']['respostas']['values']);
    }

    public function test_capacidade_considera_atribuicoes_respondidas_e_encerradas(): void
    {
        $this->voluntario->update(['cartas_limite_respostas' => 3]);
        $this->voluntario2->update(['cartas_limite_respostas' => 4]);
        $this->criarTroca($this->voluntario)->update(['status' => Carta::STATUS_RESPONDIDA]);
        $this->criarTroca($this->voluntario)->update(['status' => Carta::STATUS_ENCERRADA]);
        $this->criarCartaComRespostaAguardandoVerificacao(); // Também ocupa uma atribuição.
        $this->criarTroca($this->voluntario)->delete();
        Carta::factory()->create(['educando_participante_id' => $this->educando->id, 'voluntario_user_id' => null]);

        $this->remetente->assignRole('cartas_voluntario');
        $this->remetente->update(['cartas_limite_respostas' => 99]);
        $this->novoVoluntario()->delete();

        $this->assertSame([
            'voluntarios' => 2,
            'capacidade' => 7,
            'atribuidas' => 3,
            'saldo' => 4,
        ], app(RelatorioCartasService::class)->resumo()['metricas']);

        $this->assertSame([$this->voluntario2->id], app(RelatorioCartasService::class)->voluntariosSemCartas()->pluck('id')->all());
    }

    public function test_vinculos_incluem_nao_informados_sem_misturar_usuarios_engaja(): void
    {
        $this->voluntario->update(['cartas_tipo_vinculo' => User::VINCULO_PETROBRAS]);
        $this->voluntario2->update(['cartas_tipo_vinculo' => User::VINCULO_COMUNIDADE]);
        $this->novoVoluntario();
        $this->remetente->assignRole('cartas_voluntario');
        $this->remetente->update(['cartas_tipo_vinculo' => User::VINCULO_PETROBRAS]);

        $grafico = app(RelatorioCartasService::class)->resumo()['graficos']['vinculos'];
        $this->assertSame(['Funcionário Petrobras', 'Comunidade Freiriana', 'Não informado'], $grafico['labels']);
        $this->assertSame([1, 1, 1], $grafico['values']);
        $this->assertSame(3, $grafico['total']);
    }

    public function test_listas_tem_paginacao_independente_e_preservam_a_outra_pagina(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->criarTroca($this->novoVoluntario());
            $this->novoVoluntario();
        }

        $response = $this->actingAs($this->gestor)->get(route('cartas.relatorios.index', [
            'pendentes_page' => 2,
            'sem_cartas_page' => 2,
        ]))->assertOk();

        $this->assertSame(16, $response->viewData('pendentes')->total());
        $this->assertSame(18, $response->viewData('semCartas')->total());
        $this->assertSame(2, $response->viewData('pendentes')->currentPage());
        $this->assertSame(2, $response->viewData('semCartas')->currentPage());
        $this->assertSame(1, $response->viewData('pendentes')->count());
        $this->assertSame(3, $response->viewData('semCartas')->count());
        $this->assertStringContainsString('sem_cartas_page=2', $response->viewData('pendentes')->previousPageUrl());
        $this->assertStringContainsString('pendentes_page=2', $response->viewData('semCartas')->previousPageUrl());
    }

    public function test_paginacao_invalida_e_rejeitada_e_nomes_sao_escapados(): void
    {
        $this->actingAs($this->gestor)
            ->getJson(route('cartas.relatorios.index', ['pendentes_page' => 0, 'sem_cartas_page' => 'invalido']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pendentes_page', 'sem_cartas_page']);

        $nome = '<img src=x onerror=alert(1)>';
        $this->voluntario->update(['name' => $nome]);
        $this->actingAs($this->gestor)
            ->get(route('cartas.relatorios.index'))
            ->assertOk()
            ->assertSee(e($nome), false)
            ->assertDontSee($nome, false);
    }

    private function novoVoluntario(): User
    {
        $user = User::factory()->create([
            'sistema_origem' => User::SISTEMA_CARTAS,
            'cartas_limite_respostas' => 2,
        ]);
        $user->assignRole('cartas_voluntario');

        return $user;
    }

    private function criarTroca(User $voluntario): Carta
    {
        $carta = Carta::factory()->create([
            'educando_participante_id' => $this->educando->id,
            'voluntario_user_id' => $voluntario->id,
        ]);
        CartaMensagem::factory()->create([
            'carta_id' => $carta->id,
            'remetente_participante_id' => $this->educando->id,
            'destinatario_user_id' => $voluntario->id,
        ]);

        return $carta;
    }

    private function adicionarMensagem(Carta $carta, array $atributos = []): CartaMensagem
    {
        return CartaMensagem::factory()->doVoluntario()->create([
            'carta_id' => $carta->id,
            'rodada' => $carta->mensagens()->max('rodada') + 1,
            'remetente_user_id' => $carta->voluntario_user_id,
            'destinatario_participante_id' => $this->educando->id,
            ...$atributos,
        ]);
    }
}
