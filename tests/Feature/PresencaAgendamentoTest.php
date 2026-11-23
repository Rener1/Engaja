<?php

namespace Tests\Feature;

use App\Models\Atividade;
use App\Models\Eixo;
use App\Models\Evento;
use App\Models\Participante;
use App\Models\User;
use App\Support\AgendamentoPresenca;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PresencaAgendamentoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function criarUsuarioComDemograficos(array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'identidade_genero' => 'Mulher Cisgênero',
            'raca_cor' => 'Branca',
            'comunidade_tradicional' => 'Não',
            'faixa_etaria' => 'Adulto (18 a 59 anos)',
            'pcd' => 'Não',
            'orientacao_sexual' => 'Heterossexual',
        ], $extra));
    }

    private function criarUsuarioComPermissaoAbrir(): User
    {
        Permission::findOrCreate('presenca.abrir');
        $user = User::factory()->create();
        $user->givePermissionTo('presenca.abrir');

        return $user;
    }

    private function criarAtividade(array $atributos = []): Atividade
    {
        $eixo = Eixo::create(['nome' => 'Eixo Teste']);
        $evento = Evento::factory()->create(['eixo_id' => $eixo->id]);

        return Atividade::factory()->create(array_merge([
            'evento_id' => $evento->id,
            'dia' => '2026-10-06',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'presenca_ativa' => false,
            'presenca_abre_em' => null,
            'presenca_fecha_em' => null,
        ], $atributos));
    }

    private function verificarCamposAgendamento(TestResponse $response, string $prefixo = '', bool $ativo = false, string $abre = '', string $fecha = ''): void
    {
        $response->assertOk();
        $documento = new \DOMDocument;
        @$documento->loadHTML($response->getContent());

        $this->assertSame($abre, $documento->getElementById($prefixo.'presenca_abre_em')->getAttribute('value'));
        $this->assertSame($fecha, $documento->getElementById($prefixo.'presenca_fecha_em')->getAttribute('value'));
        $this->assertSame($ativo, $documento->getElementById($prefixo.'agendamento_presenca_ativo')->hasAttribute('checked'));
        $fieldset = (new \DOMXPath($documento))->query('//*[@data-agendamento-campos]')->item(0);
        $this->assertSame(! $ativo, $fieldset->hasAttribute('disabled'));
    }

    public function test_campos_de_agendamento_ficam_vazios_por_padrao_nas_telas_do_momento(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'UTC'));
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        foreach (['atividade.criar', 'atividade.editar'] as $permissao) {
            Permission::findOrCreate($permissao);
            $gerente->givePermissionTo($permissao);
        }
        $atividade = $this->criarAtividade();

        $this->actingAs($gerente);
        $this->verificarCamposAgendamento($this->get(route('eventos.atividades.create', $atividade->evento)));
        $this->verificarCamposAgendamento($this->get(route('atividades.edit', $atividade)));
        $this->verificarCamposAgendamento($this->get(route('atividades.show', $atividade)), 'modal_');
    }

    public function test_agendamento_existente_exibe_somente_as_datas_salvas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'UTC'));
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        Permission::findOrCreate('atividade.editar');
        $gerente->givePermissionTo('atividade.editar');
        $atividade = $this->criarAtividade(['presenca_fecha_em' => Carbon::parse('2026-10-06 15:00:00', 'UTC')]);

        $this->actingAs($gerente);
        $this->verificarCamposAgendamento($this->get(route('atividades.edit', $atividade)), ativo: true, fecha: '2026-10-06T12:00');
        $this->verificarCamposAgendamento($this->get(route('atividades.show', $atividade)), 'modal_', ativo: true, fecha: '2026-10-06T12:00');
    }

    public function test_desmarcar_agendamento_no_formulario_remove_as_datas_e_nao_as_recria_ao_voltar(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'UTC'));
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        Permission::findOrCreate('atividade.editar');
        $gerente->givePermissionTo('atividade.editar');
        $atividade = $this->criarAtividade([
            'presenca_ativa' => true,
            'presenca_abre_em' => now()->addDay(),
            'presenca_fecha_em' => now()->addDays(2),
        ]);

        $this->actingAs($gerente)->put(route('atividades.update', $atividade), [
            'descricao' => $atividade->descricao,
            'dia' => '2026-10-06',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'agendamento_presenca_ativo' => '0',
            // Campos desativados devem ser ignorados, inclusive se enviados indevidamente.
            'presenca_abre_em' => 'data-invalida',
            'presenca_fecha_em' => 'data-invalida',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $atividade->refresh();
        $this->assertNull($atividade->presenca_abre_em);
        $this->assertNull($atividade->presenca_fecha_em);
        $this->assertTrue($atividade->presencaEstaAberta());
        $this->verificarCamposAgendamento($this->get(route('atividades.edit', $atividade)));
        $this->verificarCamposAgendamento($this->get(route('atividades.show', $atividade)), 'modal_');
    }

    public function test_desmarcar_agendamento_no_modal_remove_as_datas(): void
    {
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        $atividade = $this->criarAtividade([
            'presenca_abre_em' => now()->addDay(),
            'presenca_fecha_em' => now()->addDays(2),
        ]);

        $this->actingAs($gerente)->patch(route('atividades.presenca.agendamento', $atividade), [
            'agendamento_presenca_ativo' => '0',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $atividade->refresh();
        $this->assertNull($atividade->presenca_abre_em);
        $this->assertNull($atividade->presenca_fecha_em);
        $this->assertFalse($atividade->presencaEstaAberta());
        $this->verificarCamposAgendamento($this->get(route('atividades.show', $atividade)), 'modal_');
    }

    public function test_participante_e_bloqueado_quando_presenca_estiver_fechada_no_qr_store(): void
    {
        $atividade = $this->criarAtividade(['presenca_ativa' => false]);
        $user = $this->criarUsuarioComDemograficos(['email' => 'aluno@teste.com']);
        Participante::firstOrCreate(['user_id' => $user->id]);

        $response = $this->post(route('presenca.store', $atividade), [
            'campo' => 'aluno@teste.com',
        ]);

        $response->assertRedirect(route('presenca.confirmar', $atividade));
        $response->assertSessionHas('error', 'A confirmação de presença deste momento está encerrada.');
        $this->assertDatabaseCount('presencas', 0);
    }

    public function test_participante_e_bloqueado_quando_presenca_estiver_fechada_nos_demograficos(): void
    {
        $atividade = $this->criarAtividade(['presenca_ativa' => false]);
        $user = User::factory()->create();
        Participante::firstOrCreate(['user_id' => $user->id]);

        $response = $this->post(route('presenca.demograficos', $atividade), [
            'user_token' => encrypt($user->id),
            'identidade_genero' => 'Homem Cisgênero',
            'raca_cor' => 'Parda',
            'comunidade_tradicional' => 'Não',
            'faixa_etaria' => 'Adulto (18 a 59 anos)',
            'pcd' => 'Não',
            'orientacao_sexual' => 'Heterossexual',
        ]);

        $response->assertRedirect(route('presenca.confirmar', $atividade));
        $response->assertSessionHas('error', 'A confirmação de presença deste momento está encerrada.');
        $this->assertDatabaseCount('presencas', 0);
    }

    public function test_usuario_logado_e_bloqueado_no_checkin_quando_presenca_fechada(): void
    {
        $atividade = $this->criarAtividade(['presenca_ativa' => false]);
        $user = $this->criarUsuarioComDemograficos();

        $response = $this->actingAs($user)->post(route('atividades.presenca.checkin', $atividade));

        $response->assertSessionHasErrors('checkin');
        $this->assertDatabaseCount('presencas', 0);
    }

    public function test_pagina_de_confirmacao_qr_exibe_aviso_e_esconde_formulario_quando_fechada(): void
    {
        $atividade = $this->criarAtividade(['presenca_ativa' => false]);

        $response = $this->get(route('presenca.confirmar', $atividade));

        $response->assertOk();
        $response->assertSee('Confirmação de presença encerrada');
        $response->assertDontSee('id="form-busca-presenca"', false);
    }

    public function test_toggle_manual_alterna_entre_aberta_e_fechada(): void
    {
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        $atividade = $this->criarAtividade(['presenca_ativa' => false]);

        $this->assertFalse($atividade->presencaEstaAberta());

        // 1º Toggle -> Abre
        $res1 = $this->actingAs($gerente)->patch(route('atividades.presenca.toggle', $atividade));
        $res1->assertSessionHas('success', 'Presença aberta para este momento.');
        $atividade->refresh();
        $this->assertTrue($atividade->presencaEstaAberta());
        $this->assertTrue($atividade->presenca_ativa);

        // 2º Toggle -> Fecha
        $res2 = $this->actingAs($gerente)->patch(route('atividades.presenca.toggle', $atividade));
        $res2->assertSessionHas('success', 'Presença fechada para este momento.');
        $atividade->refresh();
        $this->assertFalse($atividade->presencaEstaAberta());
        $this->assertFalse($atividade->presenca_ativa);
    }

    public function test_toggle_assincrono_retorna_json_com_o_novo_estado(): void
    {
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        $atividade = $this->criarAtividade();
        $atividade->update(['presenca_ativa' => false]);

        $this->actingAs($gerente)
            ->patchJson(route('atividades.presenca.toggle', $atividade))
            ->assertOk()
            ->assertJson(['aberta' => true]);

        $this->actingAs($gerente)
            ->patchJson(route('atividades.presenca.toggle', $atividade))
            ->assertOk()
            ->assertJson(['aberta' => false]);
    }

    public function test_usuario_sem_permissao_nao_consegue_dar_toggle(): void
    {
        $user = User::factory()->create();
        $atividade = $this->criarAtividade(['presenca_ativa' => false]);

        $response = $this->actingAs($user)->patch(route('atividades.presenca.toggle', $atividade));

        $response->assertForbidden();
    }

    public function test_agendamento_automatico_abre_e_fecha_conforme_o_tempo_avanca_sem_cron(): void
    {
        // Agendamento: abre 2026-10-06 11:00 UTC (08:00 BRT) e fecha 2026-10-06 15:00 UTC (12:00 BRT)
        $abreUtc = Carbon::parse('2026-10-06 11:00:00', 'UTC');
        $fechaUtc = Carbon::parse('2026-10-06 15:00:00', 'UTC');

        $atividade = $this->criarAtividade([
            'presenca_ativa' => false,
            'presenca_abre_em' => $abreUtc,
            'presenca_fecha_em' => $fechaUtc,
        ]);

        $aluno = $this->criarUsuarioComDemograficos(['email' => 'aluno@teste.com']);
        Participante::firstOrCreate(['user_id' => $aluno->id]);

        // Momento 1: 07:50 BRT (10:50 UTC) -> ANTES DA ABERTURA -> FECHADA
        Carbon::setTestNow(Carbon::parse('2026-10-06 10:50:00', 'UTC'));
        $this->assertFalse($atividade->presencaEstaAberta());

        $res1 = $this->post(route('presenca.store', $atividade), ['campo' => 'aluno@teste.com']);
        $res1->assertSessionHas('error');
        $this->assertDatabaseCount('presencas', 0);

        // Momento 2: 08:05 BRT (11:05 UTC) -> DURANTE O MOMENTO -> ABERTA
        Carbon::setTestNow(Carbon::parse('2026-10-06 11:05:00', 'UTC'));
        $this->assertTrue($atividade->presencaEstaAberta());

        $res2 = $this->post(route('presenca.store', $atividade), ['campo' => 'aluno@teste.com']);
        $res2->assertRedirect(route('presenca.confirmar', $atividade));
        $res2->assertSessionHas('success-presenca');
        $this->assertDatabaseCount('presencas', 1);

        // Momento 3: 12:01 BRT (15:01 UTC) -> APÓS O FECHAMENTO -> FECHADA
        Carbon::setTestNow(Carbon::parse('2026-10-06 15:01:00', 'UTC'));
        $this->assertFalse($atividade->presencaEstaAberta());

        $outroAluno = $this->criarUsuarioComDemograficos(['email' => 'outro@teste.com']);
        Participante::firstOrCreate(['user_id' => $outroAluno->id]);

        $res3 = $this->post(route('presenca.store', $atividade), ['campo' => 'outro@teste.com']);
        $res3->assertSessionHas('error', 'A confirmação de presença deste momento está encerrada.');
        $this->assertDatabaseCount('presencas', 1);
    }

    public function test_toggle_manual_consolida_agendamento_passado_antes_de_inverter(): void
    {
        $gerente = $this->criarUsuarioComPermissaoAbrir();

        // Agendamento que já passou a hora de abrir: 11:00 UTC
        $abreUtc = Carbon::parse('2026-10-06 11:00:00', 'UTC');
        $atividade = $this->criarAtividade([
            'presenca_ativa' => false,
            'presenca_abre_em' => $abreUtc,
            'presenca_fecha_em' => null,
        ]);

        // Estamos às 11:30 UTC -> Presença está efetivamente aberta
        Carbon::setTestNow(Carbon::parse('2026-10-06 11:30:00', 'UTC'));
        $this->assertTrue($atividade->presencaEstaAberta());

        // O gerente clica em "Fechar presença" (toggle)
        $this->actingAs($gerente)->patch(route('atividades.presenca.toggle', $atividade));

        $atividade->refresh();
        // Deve ter fechado a presença e limpado o timestamp já vencido
        $this->assertFalse($atividade->presencaEstaAberta());
        $this->assertFalse($atividade->presenca_ativa);
        $this->assertNull($atividade->presenca_abre_em);
    }

    public function test_salvar_agendamento_via_modal_converte_fuso_horario_para_utc(): void
    {
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        $atividade = $this->criarAtividade();

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'America/Sao_Paulo'));

        // Horário de Brasília digitado no input: 06/10 às 08:00 e 12:00
        $response = $this->actingAs($gerente)->patch(route('atividades.presenca.agendamento', $atividade), [
            'agendamento_presenca_ativo' => '1',
            'presenca_abre_em' => '2026-10-06T08:00',
            'presenca_fecha_em' => '2026-10-06T12:00',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $atividade->refresh();
        // Em UTC (Brasília é UTC-3): 08:00 BRT -> 11:00 UTC; 12:00 BRT -> 15:00 UTC
        $this->assertEquals('2026-10-06 11:00:00', $atividade->presenca_abre_em->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-06 15:00:00', $atividade->presenca_fecha_em->format('Y-m-d H:i:s'));
    }

    public function test_agendamento_com_fechamento_anterior_a_abertura_retorna_erro_de_validacao(): void
    {
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        $atividade = $this->criarAtividade();

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'America/Sao_Paulo'));

        $response = $this->actingAs($gerente)->patch(route('atividades.presenca.agendamento', $atividade), [
            'presenca_abre_em' => '2026-10-06T12:00',
            'presenca_fecha_em' => '2026-10-06T08:00',
        ]);

        $response->assertSessionHasErrorsIn('agendamentoPresenca', ['presenca_fecha_em']);
    }

    public function test_agendamento_so_com_fechamento_no_futuro_e_aceito(): void
    {
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        $atividade = $this->criarAtividade();

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'America/Sao_Paulo'));

        $response = $this->actingAs($gerente)->patch(route('atividades.presenca.agendamento', $atividade), [
            'presenca_abre_em' => '',
            'presenca_fecha_em' => '2026-10-05T18:00',
        ]);

        $response->assertSessionHasNoErrors();
        $atividade->refresh();
        $this->assertNull($atividade->presenca_abre_em);
        $this->assertEquals('2026-10-05 21:00:00', $atividade->presenca_fecha_em->format('Y-m-d H:i:s'));
    }

    public function test_agendamento_no_mesmo_dia_com_horarios_diferentes_do_momento_e_aceito(): void
    {
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        $atividade = $this->criarAtividade();

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'America/Sao_Paulo'));

        // Abre e fecha hoje, em horários livres (independentes do início/fim do momento).
        $response = $this->actingAs($gerente)->patch(route('atividades.presenca.agendamento', $atividade), [
            'presenca_abre_em' => '2026-10-05T17:36',
            'presenca_fecha_em' => '2026-10-05T19:33',
        ]);

        $response->assertSessionHasNoErrors();
        $atividade->refresh();
        $this->assertEquals('2026-10-05 20:36:00', $atividade->presenca_abre_em->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-05 22:33:00', $atividade->presenca_fecha_em->format('Y-m-d H:i:s'));
    }

    public function test_sugestao_ignora_horarios_que_ja_passaram(): void
    {
        $atividade = $this->criarAtividade();
        $atividade->forceFill(['dia' => '2026-08-10', 'hora_inicio' => '17:36:00', 'hora_fim' => '19:33:00']);

        Carbon::setTestNow(Carbon::parse('2026-10-06 16:00:00', 'America/Sao_Paulo'));

        $sugestao = AgendamentoPresenca::sugestao($atividade);

        $this->assertNull($sugestao['abre']);
        $this->assertNull($sugestao['fecha']);

        $atividade->forceFill(['dia' => '2026-10-06']);
        $sugestao = AgendamentoPresenca::sugestao($atividade);

        $this->assertEquals('2026-10-06T17:36', $sugestao['abre']);
        $this->assertEquals('2026-10-06T19:33', $sugestao['fecha']);
    }

    public function test_remover_agendamento_limpa_campos(): void
    {
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        $atividade = $this->criarAtividade([
            'presenca_abre_em' => now()->addDay(),
            'presenca_fecha_em' => now()->addDays(2),
        ]);

        $response = $this->actingAs($gerente)->delete(route('atividades.presenca.agendamento.limpar', $atividade));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Agendamento da presença removido.');

        $atividade->refresh();
        $this->assertNull($atividade->presenca_abre_em);
        $this->assertNull($atividade->presenca_fecha_em);
    }

    public function test_atualizacao_de_atividade_com_agendamento_salva_corretamente(): void
    {
        Permission::findOrCreate('atividade.editar');
        Permission::findOrCreate('presenca.abrir');

        $gerente = User::factory()->create();
        $gerente->givePermissionTo(['atividade.editar', 'presenca.abrir']);

        $atividade = $this->criarAtividade();

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'America/Sao_Paulo'));

        $response = $this->actingAs($gerente)->put(route('atividades.update', $atividade), [
            'descricao' => 'Momento com agendamento',
            'agendamento_presenca_ativo' => '1',
            'dia' => '2026-10-06',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'presenca_abre_em' => '2026-10-06T08:00',
            'presenca_fecha_em' => '2026-10-06T12:00',
        ]);

        $response->assertRedirect();

        $atividade->refresh();
        $this->assertEquals('2026-10-06 11:00:00', $atividade->presenca_abre_em->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-06 15:00:00', $atividade->presenca_fecha_em->format('Y-m-d H:i:s'));
    }

    public function test_usuario_sem_permissao_presenca_abrir_nao_consegue_alterar_agendamento_pelo_update_de_atividade(): void
    {
        Permission::findOrCreate('atividade.editar');
        $usuario = User::factory()->create();
        $usuario->givePermissionTo('atividade.editar');

        $dataAbreOriginal = now()->addDays(5)->startOfMinute();
        $atividade = $this->criarAtividade([
            'presenca_abre_em' => $dataAbreOriginal,
        ]);

        $response = $this->actingAs($usuario)->put(route('atividades.update', $atividade), [
            'descricao' => 'Momento alterado',
            'agendamento_presenca_ativo' => '0',
            'dia' => '2026-10-06',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'presenca_abre_em' => '2026-10-06T08:00', // Tentativa sem permissão
        ]);

        $response->assertRedirect();

        $atividade->refresh();
        // Não foi alterado, manteve o original
        $this->assertEquals($dataAbreOriginal->format('Y-m-d H:i:s'), $atividade->presenca_abre_em->format('Y-m-d H:i:s'));
    }

    public function test_criacao_de_atividade_com_agendamento_salva_corretamente(): void
    {
        Permission::findOrCreate('atividade.criar');
        Permission::findOrCreate('presenca.abrir');

        $gerente = User::factory()->create();
        $gerente->givePermissionTo(['atividade.criar', 'presenca.abrir']);

        $eixo = Eixo::create(['nome' => 'Eixo Teste']);
        $evento = Evento::factory()->create(['eixo_id' => $eixo->id]);

        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'America/Sao_Paulo'));

        $response = $this->actingAs($gerente)->post(route('eventos.atividades.store', $evento), [
            'descricao' => 'Novo Momento Agendado',
            'agendamento_presenca_ativo' => '1',
            'dia' => '2026-10-06',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'presenca_abre_em' => '2026-10-06T08:00',
            'presenca_fecha_em' => '2026-10-06T12:00',
        ]);

        $response->assertRedirect(route('eventos.show', $evento));

        $atividade = Atividade::where('descricao', 'Novo Momento Agendado')->firstOrFail();
        $this->assertEquals('2026-10-06 11:00:00', $atividade->presenca_abre_em->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-10-06 15:00:00', $atividade->presenca_fecha_em->format('Y-m-d H:i:s'));
        $this->assertFalse($atividade->presenca_ativa);
    }

    public function test_bloqueia_salvar_criacao_e_edicao_quando_agendamento_ativo_sem_horarios(): void
    {
        foreach (['atividade.criar', 'atividade.editar', 'presenca.abrir'] as $permissao) {
            Permission::findOrCreate($permissao);
        }

        $gerente = User::factory()->create();
        $gerente->givePermissionTo(['atividade.criar', 'atividade.editar', 'presenca.abrir']);

        $abreOriginal = Carbon::parse('2026-10-06 11:00:00', 'UTC');
        $atividade = $this->criarAtividade(['presenca_abre_em' => $abreOriginal]);

        // 1. Criar momento com "Ativar agendamento de presença" marcado e ambos os horários vazios
        $resCriar = $this->actingAs($gerente)
            ->from(route('eventos.atividades.create', $atividade->evento))
            ->post(route('eventos.atividades.store', $atividade->evento), [
                'descricao' => 'Momento Bloqueado',
                'agendamento_presenca_ativo' => '1',
                'dia' => '2026-10-06',
                'hora_inicio' => '08:00',
                'hora_fim' => '12:00',
                'presenca_abre_em' => '',
                'presenca_fecha_em' => '',
            ]);

        $resCriar->assertRedirect(route('eventos.atividades.create', $atividade->evento));
        $resCriar->assertSessionHasErrors([
            'presenca_abre_em' => AgendamentoPresenca::MENSAGEM_SEM_HORARIO,
        ]);
        $this->assertDatabaseMissing('atividades', ['descricao' => 'Momento Bloqueado']);

        // 2. Editar momento com "Ativar agendamento de presença" marcado e ambos os horários vazios
        $resEditar = $this->actingAs($gerente)
            ->from(route('atividades.edit', $atividade))
            ->put(route('atividades.update', $atividade), [
                'descricao' => 'Momento Editado Sem Horarios',
                'agendamento_presenca_ativo' => '1',
                'dia' => '2026-10-06',
                'hora_inicio' => '08:00',
                'hora_fim' => '12:00',
                'presenca_abre_em' => '',
                'presenca_fecha_em' => '',
            ]);

        $resEditar->assertRedirect(route('atividades.edit', $atividade));
        $resEditar->assertSessionHasErrors([
            'presenca_abre_em' => AgendamentoPresenca::MENSAGEM_SEM_HORARIO,
        ]);

        $atividade->refresh();
        $this->assertEquals($abreOriginal->format('Y-m-d H:i:s'), $atividade->presenca_abre_em->format('Y-m-d H:i:s'));
    }

    public function test_criacao_de_atividade_sem_agendamento_de_abertura_cria_com_presenca_aberta(): void
    {
        Permission::findOrCreate('atividade.criar');
        Permission::findOrCreate('presenca.abrir');

        $gerente = User::factory()->create();
        $gerente->givePermissionTo(['atividade.criar', 'presenca.abrir']);

        $eixo = Eixo::create(['nome' => 'Eixo Teste']);
        $evento = Evento::factory()->create(['eixo_id' => $eixo->id]);

        // 1. Sem preencher nada no agendamento: deve criar com presença ABERTA
        $this->actingAs($gerente)->post(route('eventos.atividades.store', $evento), [
            'descricao' => 'Momento Sem Agendamento',
            'dia' => '2026-10-06',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'presenca_abre_em' => '',
            'presenca_fecha_em' => '',
        ])->assertRedirect(route('eventos.show', $evento));

        $atividadeSemAgendamento = Atividade::where('descricao', 'Momento Sem Agendamento')->firstOrFail();
        $this->assertTrue($atividadeSemAgendamento->presenca_ativa);
        $this->assertNull($atividadeSemAgendamento->presenca_abre_em);
        $this->assertNull($atividadeSemAgendamento->presenca_fecha_em);
        $this->assertTrue($atividadeSemAgendamento->presencaEstaAberta());

        // 2. Preenchendo apenas horário de fechamento futuro (sem data de abertura): deve criar ABERTA
        Carbon::setTestNow(Carbon::parse('2026-10-06 09:00:00', 'America/Sao_Paulo'));

        $this->actingAs($gerente)->post(route('eventos.atividades.store', $evento), [
            'descricao' => 'Momento So Com Fechamento Futuro',
            'dia' => '2026-10-06',
            'hora_inicio' => '08:00',
            'hora_fim' => '12:00',
            'presenca_abre_em' => '',
            'presenca_fecha_em' => '2026-10-06T12:00',
        ])->assertRedirect(route('eventos.show', $evento));

        $atividadeComFechamento = Atividade::where('descricao', 'Momento So Com Fechamento Futuro')->firstOrFail();
        $this->assertTrue($atividadeComFechamento->presenca_ativa);
        $this->assertNull($atividadeComFechamento->presenca_abre_em);
        $this->assertNotNull($atividadeComFechamento->presenca_fecha_em);
        $this->assertTrue($atividadeComFechamento->presencaEstaAberta());
    }

    public function test_comando_sincronizar_agendamentos_consolida_presencas_vencidas(): void
    {
        // Atividade 1: abre_em vencido -> deve virar aberta e limpar abre_em
        $atv1 = $this->criarAtividade([
            'presenca_ativa' => false,
            'presenca_abre_em' => now()->subMinutes(10),
            'presenca_fecha_em' => null,
        ]);

        // Atividade 2: fecha_em vencido -> deve virar fechada e limpar fecha_em
        $atv2 = $this->criarAtividade([
            'presenca_ativa' => true,
            'presenca_abre_em' => null,
            'presenca_fecha_em' => now()->subMinutes(5),
        ]);

        // Atividade 3: horários futuros -> NÃO deve ser alterada
        $futuroAbre = now()->addHours(2);
        $atv3 = $this->criarAtividade([
            'presenca_ativa' => false,
            'presenca_abre_em' => $futuroAbre,
            'presenca_fecha_em' => null,
        ]);

        $this->artisan('presenca:sincronizar-agendamentos')
            ->expectsOutputToContain('Total de momentos sincronizados: 2.')
            ->assertSuccessful();

        $atv1->refresh();
        $this->assertTrue($atv1->presenca_ativa);
        $this->assertNull($atv1->presenca_abre_em);

        $atv2->refresh();
        $this->assertFalse($atv2->presenca_ativa);
        $this->assertNull($atv2->presenca_fecha_em);

        $atv3->refresh();
        $this->assertFalse($atv3->presenca_ativa);
        $this->assertNotNull($atv3->presenca_abre_em);
    }

    public function test_tela_gerenciamento_presencas_requer_permissao_presenca_abrir(): void
    {
        $usuarioSemPermissao = User::factory()->create();

        $response = $this->actingAs($usuarioSemPermissao)->get(route('presencas.gerenciamento'));
        $response->assertForbidden();

        $gerente = $this->criarUsuarioComPermissaoAbrir();
        $responseGerente = $this->actingAs($gerente)->get(route('presencas.gerenciamento'));
        $responseGerente->assertOk();
        $responseGerente->assertSee('Controle de Confirmação de Presenças');
    }

    public function test_tela_gerenciamento_presencas_lista_momentos_e_aplica_filtros(): void
    {
        $gerente = $this->criarUsuarioComPermissaoAbrir();

        $eixo = Eixo::create(['nome' => 'Eixo Teste']);
        $eventoA = Evento::factory()->create(['eixo_id' => $eixo->id, 'nome' => 'Ação Alfa']);
        $eventoB = Evento::factory()->create(['eixo_id' => $eixo->id, 'nome' => 'Ação Beta']);

        $atvAberta = $this->criarAtividade([
            'evento_id' => $eventoA->id,
            'descricao' => 'Oficina Leitura Aberta',
            'presenca_ativa' => true,
        ]);

        $atvFechada = $this->criarAtividade([
            'evento_id' => $eventoB->id,
            'descricao' => 'Palestra Fechada',
            'presenca_ativa' => false,
        ]);

        // Listagem geral
        $resGeral = $this->actingAs($gerente)->get(route('presencas.gerenciamento'));
        $resGeral->assertOk();
        $resGeral->assertSee('Oficina Leitura Aberta');
        $resGeral->assertSee('Palestra Fechada');

        // Filtro por status=aberta
        $resAberta = $this->actingAs($gerente)->get(route('presencas.gerenciamento', ['status' => 'aberta']));
        $resAberta->assertOk();
        $resAberta->assertSee('Oficina Leitura Aberta');
        $resAberta->assertDontSee('Palestra Fechada');

        // Filtro por status=fechada
        $resFechada = $this->actingAs($gerente)->get(route('presencas.gerenciamento', ['status' => 'fechada']));
        $resFechada->assertOk();
        $resFechada->assertSee('Palestra Fechada');
        $resFechada->assertDontSee('Oficina Leitura Aberta');

        // Filtro textual por q
        $resBusca = $this->actingAs($gerente)->get(route('presencas.gerenciamento', ['q' => 'Alfa']));
        $resBusca->assertOk();
        $resBusca->assertSee('Oficina Leitura Aberta');
        $resBusca->assertDontSee('Palestra Fechada');
    }

    public function test_cards_do_gerenciamento_contabilizam_todos_os_momentos_independente_da_pagina_e_dos_filtros(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'UTC'));
        $gerente = $this->criarUsuarioComPermissaoAbrir();
        $eixo = Eixo::create(['nome' => 'Eixo Teste']);
        $evento = Evento::factory()->create(['eixo_id' => $eixo->id]);

        Atividade::factory()->count(30)->presencaFechada()->create(['evento_id' => $evento->id]);

        // Inclui transições vencidas, o instante exato da transição e estado manual
        // com abertura futura, conforme presencaEstaAberta().
        $estados = [
            [false, null, null],
            [true, null, null],
            [false, now()->subHour(), null],
            [true, now()->addHour(), null],
            [false, now()->addHour(), null],
            [true, now()->subHour(), now()],
            [false, now(), now()->addHour()],
            [true, null, now()->subHour()],
            [true, null, now()->addHour()],
        ];

        foreach ($estados as [$ativa, $abre, $fecha]) {
            Atividade::factory()->create([
                'evento_id' => $evento->id,
                'presenca_ativa' => $ativa,
                'presenca_abre_em' => $abre,
                'presenca_fecha_em' => $fecha,
            ]);
        }

        // Momentos excluídos ou de ações excluídas não entram nos totais.
        $atributosExcluidos = [
            'presenca_ativa' => true,
            'presenca_fecha_em' => now()->addHour(),
        ];
        Atividade::factory()->create($atributosExcluidos + ['evento_id' => $evento->id])->delete();
        $eventoExcluido = Evento::factory()->create(['eixo_id' => $eixo->id]);
        Atividade::factory()->create($atributosExcluidos + ['evento_id' => $eventoExcluido->id]);
        $eventoExcluido->delete();

        foreach ([[], ['page' => 2], ['status' => 'aberta', 'q' => 'Busca sem resultados']] as $filtros) {
            $response = $this->actingAs($gerente)->get(route('presencas.gerenciamento', $filtros));

            $response->assertOk()
                ->assertViewHas('totalAtividades', 39)
                ->assertViewHas('totalAbertas', 5)
                ->assertViewHas('totalFechadas', 34)
                ->assertViewHas('comAgendamentoFuturo', 4)
                ->assertDontSee('Nesta Listagem');

            $quantidadeNaPagina = isset($filtros['q']) ? 0 : (isset($filtros['page']) ? 14 : 25);
            $this->assertCount($quantidadeNaPagina, $response->viewData('atividades'));
        }
    }

    public function test_cards_do_gerenciamento_retornam_zero_quando_nao_ha_momentos(): void
    {
        $gerente = $this->criarUsuarioComPermissaoAbrir();

        $this->actingAs($gerente)->get(route('presencas.gerenciamento'))
            ->assertOk()
            ->assertViewHas('totalAtividades', 0)
            ->assertViewHas('totalAbertas', 0)
            ->assertViewHas('totalFechadas', 0)
            ->assertViewHas('comAgendamentoFuturo', 0);
    }
}
