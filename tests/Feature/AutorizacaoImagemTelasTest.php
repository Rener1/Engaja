<?php

namespace Tests\Feature;

use App\Services\AutorizacaoImagemContexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAutorizacaoImagem;
use Tests\TestCase;

class AutorizacaoImagemTelasTest extends TestCase
{
    use InteractsWithAutorizacaoImagem, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararAutorizacoes();
    }

    public function test_gestao_sem_acao_selecionada_mostra_hero_filtros_e_estado_vazio(): void
    {
        $this->actingAs($this->gestor)
            ->get(route('autorizacoes-imagem.index'))
            ->assertOk()
            ->assertSee('eg-hero', false)
            ->assertSee('eg-filter-card', false)
            ->assertSee('Selecione uma ação pedagógica')
            ->assertDontSee('grid-autorizacoes-imagem', false)
            ->assertDontSee('Baixar planilha desta consulta');
    }

    public function test_gestao_com_acao_mostra_totais_tabela_e_modal_de_resposta(): void
    {
        $this->vinculo($this->pessoa);
        $this->respostaDaGestao($this->pessoa, true);

        $this->actingAs($this->gestor)
            ->get(route('autorizacoes-imagem.index', ['evento_id' => $this->acao->id]))
            ->assertOk()
            ->assertSee('Baixar planilha desta consulta')
            ->assertSeeInOrder(['eg-stat-card', 'Não respondida', 'eg-stat-card', 'Autorizada', 'eg-stat-card', 'Não autorizada'], false)
            ->assertSee('id="grid-autorizacoes-imagem"', false)
            ->assertSee('#modalAutorizacaoImagem-'.$this->pessoa->participante->id.'\\u0022&gt;Alterar resposta', false)
            ->assertSee('id="modalAutorizacaoImagem-'.$this->pessoa->participante->id.'"', false)
            ->assertSee('eg-modal-header', false)
            ->assertSee('form="formAutorizacaoImagem-'.$this->pessoa->participante->id.'"', false)
            ->assertSee('Histórico das respostas')
            ->assertSee('Limpar filtros');
    }

    public function test_minhas_autorizacoes_usa_hero_cards_e_modal(): void
    {
        $this->vinculo($this->pessoa);

        $this->actingAs($this->pessoa)
            ->get(route('profile.autorizacoes-imagem'))
            ->assertOk()
            ->assertSee('eg-hero', false)
            ->assertSee('eg-section-card', false)
            ->assertSee('Não respondida')
            ->assertSee('id="modalAutorizacaoImagem-'.$this->acao->id.'"', false)
            ->assertSee('form="formAutorizacaoImagem-'.$this->acao->id.'"', false);
    }

    public function test_minhas_autorizacoes_sem_acoes_mostra_estado_vazio(): void
    {
        $this->actingAs($this->pessoa)
            ->get(route('profile.autorizacoes-imagem'))
            ->assertOk()
            ->assertSee('Nenhuma ação para responder')
            ->assertDontSee('modalAutorizacaoImagem-', false);
    }

    public function test_modal_pos_presenca_usa_cabecalho_roxo_e_botoes_no_rodape(): void
    {
        $this->actingAs($this->pessoa)
            ->withSession([AutorizacaoImagemContexto::FLASH_KEY => [
                'participante_nome' => $this->pessoa->name,
                'acao_nome' => $this->acao->nome,
                'termo_texto' => 'Texto do termo para o modal.',
                'token' => 'token-teste',
                'atividade_id' => $this->momento->id,
            ]])
            ->get(route('profile.autorizacoes-imagem'))
            ->assertOk()
            ->assertSee('id="autorizacaoImagemPresencaModal"', false)
            ->assertSee('eg-modal-header', false)
            ->assertSee('aria-label="Responder depois"', false)
            ->assertSee('id="autorizacaoImagemConfirmar" form="autorizacaoImagemPresencaForm"', false)
            ->assertSee('Texto do termo para o modal.');
    }
}
