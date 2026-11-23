<?php

namespace Tests\Feature\Cartas;

use App\Models\Cartas\Carta;
use App\Models\Cartas\CartaEvento;
use App\Models\Cartas\CartaMensagem;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;

class CartaAprovacaoLoteTest extends CartasBaseTest
{
    #[DataProvider('managementRoles')]
    public function test_gestor_e_admin_aprovam_apenas_as_respostas_selecionadas(string $role): void
    {
        $this->gestor->syncRoles([$role]);
        [$carta1, $mensagem1] = $this->criarCartaComRespostaAguardandoVerificacao();
        [$carta2, $mensagem2] = $this->criarCartaComRespostaAguardandoVerificacao();
        [, $naoSelecionada] = $this->criarCartaComRespostaAguardandoVerificacao();
        $mensagem1->update(['parecer_verificacao' => 'Parecer anterior']);
        $origem = route('cartas.dashboard', ['status' => 'pendente', 'q' => 'Ana', 'page' => 2]);

        $response = $this->actingAs($this->gestor)->from($origem)
            ->post(route('cartas.mensagens.approve-batch'), [
                'mensagem_ids' => [$mensagem1->id, $mensagem2->id],
            ]);

        $response->assertRedirect($origem);
        $response->assertSessionHas('status', '2 respostas aprovadas.');

        foreach ([[$carta1, $mensagem1], [$carta2, $mensagem2]] as [$carta, $mensagem]) {
            $mensagem->refresh();
            $this->assertSame(CartaMensagem::STATUS_APROVADA, $mensagem->status);
            $this->assertEquals($this->gestor->id, $mensagem->verificada_por);
            $this->assertNotNull($mensagem->verificada_em);
            $this->assertNull($mensagem->parecer_verificacao);
            $this->assertSame(Carta::STATUS_RESPONDIDA, $carta->fresh()->status);
            $evento = CartaEvento::where('carta_mensagem_id', $mensagem->id)->sole();
            $this->assertSame(CartaEvento::TIPO_MENSAGEM_VERIFICADA, $evento->tipo);
            $this->assertEquals($this->gestor->id, $evento->user_id);
            $this->assertSame(CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO, $evento->dados_antes['mensagem_status']);
            $this->assertSame(Carta::STATUS_RESPONDIDA, $evento->dados_depois['carta_status']);
        }

        $this->assertSame(CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO, $naoSelecionada->fresh()->status);
        $this->assertDatabaseCount('carta_eventos', 2);
    }

    public static function managementRoles(): array
    {
        return [['cartas_gestao'], ['cartas_admin']];
    }

    public function test_reenvio_do_lote_nao_duplica_aprovacoes_nem_auditoria(): void
    {
        [, $mensagem] = $this->criarCartaComRespostaAguardandoVerificacao();
        $data = ['mensagem_ids' => [$mensagem->id]];
        $this->actingAs($this->gestor)->post(route('cartas.mensagens.approve-batch'), $data)
            ->assertSessionHas('status', '1 resposta aprovada.');
        $verificadaEm = $mensagem->fresh()->verificada_em;
        $this->travel(1)->minutes();

        $this->post(route('cartas.mensagens.approve-batch'), $data)
            ->assertSessionHas('status', '0 respostas aprovadas. 1 resposta não foi aprovada porque sua situação mudou ou ela não está mais disponível. Atualizamos a listagem.');

        $this->assertTrue($verificadaEm->equalTo($mensagem->fresh()->verificada_em));
        $this->assertDatabaseCount('carta_eventos', 1);
    }

    public function test_lote_informa_respostas_aprovadas_ajustadas_e_removidas_desde_a_selecao(): void
    {
        [, $valida] = $this->criarCartaComRespostaAguardandoVerificacao();
        [, $aprovada] = $this->criarCartaComRespostaAguardandoVerificacao();
        [, $ajustada] = $this->criarCartaComRespostaAguardandoVerificacao();
        [$cartaRemovida, $removida] = $this->criarCartaComRespostaAguardandoVerificacao();
        [, $mensagemRemovida] = $this->criarCartaComRespostaAguardandoVerificacao();

        $this->actingAs($this->gestor)->post(route('cartas.mensagens.approve', $aprovada))->assertRedirect();
        $this->post(route('cartas.mensagens.adjustment', $ajustada), ['parecer_verificacao' => 'Revisar'])->assertRedirect();
        $cartaRemovida->delete();
        $mensagemRemovida->delete();

        $this->post(route('cartas.mensagens.approve-batch'), [
            'mensagem_ids' => [$valida->id, $aprovada->id, $ajustada->id, $removida->id, $mensagemRemovida->id],
        ])->assertSessionHas('status', '1 resposta aprovada. 4 respostas não foram aprovadas porque sua situação mudou ou elas não estão mais disponíveis. Atualizamos a listagem.');

        $this->assertSame(CartaMensagem::STATUS_APROVADA, $valida->fresh()->status);
        $this->assertSame(CartaMensagem::STATUS_AJUSTE_SOLICITADO, $ajustada->fresh()->status);
        $this->assertSame(CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO, $removida->fresh()->status);
        $this->assertDatabaseCount('carta_eventos', 3);
    }

    public function test_lote_nao_aprova_mensagem_antiga_quando_existe_uma_mais_recente(): void
    {
        [$carta, $antiga] = $this->criarCartaComRespostaAguardandoVerificacao();
        $recente = $antiga->replicate();
        $recente->rodada = 2;
        $recente->save();

        $this->actingAs($this->gestor)->post(route('cartas.mensagens.approve-batch'), [
            'mensagem_ids' => [$antiga->id],
        ])->assertSessionHas('status', fn ($status) => str_starts_with($status, '0 respostas aprovadas.'));

        $this->assertSame(CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO, $antiga->fresh()->status);
        $this->assertSame(CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO, $recente->fresh()->status);
        $this->assertSame(Carta::STATUS_AGUARDANDO_VERIFICACAO, $carta->fresh()->status);
        $this->assertDatabaseCount('carta_eventos', 0);
    }

    public function test_lote_rejeita_respostas_nao_enviadas_e_mensagens_de_educando(): void
    {
        [, $naoEnviada] = $this->criarCartaComRespostaAguardandoVerificacao();
        $naoEnviada->update(['enviada_em' => null]);
        $cartaEducando = $this->criarCartaParaVoluntario();
        $educando = $cartaEducando->mensagens()->firstOrFail();
        $educando->update(['status' => CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO]);

        $this->actingAs($this->gestor)->post(route('cartas.mensagens.approve-batch'), [
            'mensagem_ids' => [$naoEnviada->id, $educando->id, 999999],
        ])->assertSessionHas('status', fn ($status) => str_starts_with($status, '0 respostas aprovadas. 3 respostas não foram aprovadas'));

        $this->assertDatabaseCount('carta_eventos', 0);
    }

    public function test_voluntario_nao_pode_aprovar_em_lote(): void
    {
        [, $mensagem] = $this->criarCartaComRespostaAguardandoVerificacao();

        $this->actingAs($this->voluntario)->post(route('cartas.mensagens.approve-batch'), [
            'mensagem_ids' => [$mensagem->id],
        ])->assertForbidden();

        $this->assertSame(CartaMensagem::STATUS_AGUARDANDO_VERIFICACAO, $mensagem->fresh()->status);
        $this->assertDatabaseCount('carta_eventos', 0);
    }

    #[DataProvider('invalidSelections')]
    public function test_lote_valida_a_selecao(array $data, string $error): void
    {
        $this->actingAs($this->gestor)->post(route('cartas.mensagens.approve-batch'), $data)
            ->assertSessionHasErrors($error);
        $this->assertDatabaseCount('carta_eventos', 0);
    }

    public static function invalidSelections(): array
    {
        return [
            'sem seleção' => [[], 'mensagem_ids'],
            'seleção vazia' => [['mensagem_ids' => []], 'mensagem_ids'],
            'não é lista' => [['mensagem_ids' => '1'], 'mensagem_ids'],
            'mais de uma página' => [['mensagem_ids' => range(1, 16)], 'mensagem_ids'],
            'duplicada' => [['mensagem_ids' => [1, 1]], 'mensagem_ids.0'],
            'id inválido' => [['mensagem_ids' => ['abc']], 'mensagem_ids.0'],
        ];
    }

    public function test_listagem_habilita_apenas_respostas_pendentes_e_inicia_sem_selecao(): void
    {
        [, $pendente] = $this->criarCartaComRespostaAguardandoVerificacao();
        $this->criarCartaParaVoluntario();
        $this->criarCartaComRespostaComAjusteSolicitado();
        [, $naoEnviada] = $this->criarCartaComRespostaAguardandoVerificacao();
        $naoEnviada->update(['enviada_em' => null]);

        $response = $this->actingAs($this->gestor)->get(route('cartas.dashboard'));
        $response->assertOk();
        $response->assertSee('Baixar cartas PDF');
        $response->assertSee('(todas as páginas)');
        $response->assertSee('0 respostas selecionadas nesta página');
        $response->assertSee('Esta troca não possui resposta aguardando aprovação');
        $response->assertDontSee('name="carta_ids[]"', false);

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new DOMXPath($dom);
        $inputs = $xpath->query('//input[@name="mensagem_ids[]"]');
        $this->assertCount(4, $inputs);
        $habilitadas = [];
        foreach ($inputs as $input) {
            $this->assertFalse($input->hasAttribute('checked'));
            $this->assertSame('approveBatchForm', $input->getAttribute('form'));
            if (! $input->hasAttribute('disabled')) {
                $habilitadas[] = (int) $input->getAttribute('value');
            }
        }
        $this->assertSame([$pendente->id], $habilitadas);
        $this->assertCount(1, $xpath->query('//button[@id="btnApproveBatch"][@disabled]'));
        $this->assertCount(1, $xpath->query('//button[@id="confirmApproveBatch"][@disabled]'));
        $this->assertCount(1, $xpath->query('//input[@id="selectAllApprovals"][not(@checked)][not(@disabled)]'));
    }

    public function test_gestor_lista_15_cartas_por_pagina_inclusive_na_busca_ajax(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->criarCartaParaVoluntario();
        }

        $this->actingAs($this->gestor)->get(route('cartas.dashboard'))
            ->assertOk()
            ->assertViewHas('cartas', fn ($cartas) => $cartas->perPage() === 15 && $cartas->count() === 15 && $cartas->total() === 16);

        $response = $this->get(route('cartas.dashboard', ['page' => 2]), ['X-Requested-With' => 'XMLHttpRequest']);
        $response->assertOk()
            ->assertViewHas('cartas', fn ($cartas) => $cartas->perPage() === 15 && $cartas->count() === 1);
        $response->assertSee('id="selectAllApprovals"', false);
        $response->assertDontSee('id="approveBatchForm"', false);
    }
}
