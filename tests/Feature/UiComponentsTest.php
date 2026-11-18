<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Componentes de UI compartilhados (resources/views/components/ui/*):
 * hero roxo, card de filtros, select, segmented, stat card, empty state e
 * cabeçalho de modal — o padrão visual extraído de /avaliacoes-consolidadas.
 */
class UiComponentsTest extends TestCase
{
    public function test_page_hero_renderiza_eyebrow_titulo_subtitulo_e_acoes(): void
    {
        $view = $this->blade(
            '<x-ui.page-hero eyebrow="Relatórios" eyebrow-icon="bar-chart" title="Painel" subtitle="Resumo geral">
                <x-slot:actions><a href="#" class="eg-hero-btn">Exportar</a></x-slot>
            </x-ui.page-hero>'
        );

        $view->assertSee('class="eg-hero eg-hero--plain"', false)
            ->assertSee('<i class="bi bi-bar-chart me-1"></i>Relatórios', false)
            ->assertSee('<h1 class="eg-hero__title">Painel</h1>', false)
            ->assertSee('<p class="eg-hero__subtitle">Resumo geral</p>', false)
            ->assertSee('<div class="eg-hero__actions">', false)
            ->assertSee('Exportar');
    }

    public function test_page_hero_contained_usa_container_e_omite_blocos_vazios(): void
    {
        $view = $this->blade('<x-ui.page-hero contained title="Só título" />');

        $view->assertSee('<div class="container">', false)
            ->assertDontSee('eg-hero--plain', false)
            ->assertDontSee('eg-hero__eyebrow', false)
            ->assertDontSee('eg-hero__subtitle', false)
            ->assertDontSee('eg-hero__actions', false)
            ->assertDontSee('eg-hero__meta', false);
    }

    public function test_page_hero_aceita_titulo_com_html_via_slot_heading(): void
    {
        $view = $this->blade(
            '<x-ui.page-hero><x-slot:heading>Momento <span class="badge">Ativo</span></x-slot></x-ui.page-hero>'
        );

        $view->assertSee('<h1 class="eg-hero__title">Momento <span class="badge">Ativo</span></h1>', false);
    }

    public function test_select_repassa_atributos_e_mostra_chevron(): void
    {
        $view = $this->blade(
            '<x-ui.select name="evento_id" id="filtro-evento" class="js-autosubmit" onchange="this.form.submit()">
                <option value="1">Ação</option>
            </x-ui.select>'
        );

        $view->assertSee('name="evento_id"', false)
            ->assertSee('id="filtro-evento"', false)
            ->assertSee('class="eg-select js-autosubmit"', false)
            ->assertSee('onchange="this.form.submit()"', false)
            ->assertSee('eg-select-chevron', false);
    }

    public function test_select_multiplo_nao_mostra_chevron(): void
    {
        $view = $this->blade(
            '<x-ui.select name="acoes[]" multiple data-multiselect data-placeholder="Todas"><option>1</option></x-ui.select>'
        );

        $view->assertSee('data-multiselect', false)
            ->assertSee('multiple', false)
            ->assertDontSee('eg-select-chevron', false);
    }

    public function test_filter_card_e_filter_field_renderizam_label_com_icone(): void
    {
        $view = $this->blade(
            '<x-ui.filter-card class="mb-5"><x-ui.filter-field class="col-md-4" label="Município" icon="geo-alt" for="mun">x</x-ui.filter-field></x-ui.filter-card>'
        );

        $view->assertSee('class="eg-filter-card mb-5"', false)
            ->assertSee('<div class="col-md-4">', false)
            ->assertSee('for="mun"', false)
            ->assertSee('class="eg-filter-label"', false)
            ->assertSee('bi-geo-alt', false)
            ->assertSee('Município');
    }

    public function test_input_aplica_classe_e_tipo(): void
    {
        $view = $this->blade('<x-ui.input type="date" name="de" value="2026-01-01" />');

        $view->assertSee('type="date"', false)
            ->assertSee('class="eg-input"', false)
            ->assertSee('name="de"', false)
            ->assertSee('value="2026-01-01"', false);
    }

    public function test_segmented_modo_formulario_marca_opcao_ativa_e_input_hidden(): void
    {
        $view = $this->blade(
            '<x-ui.segmented name="agrupamento" autosubmit :options="$opcoes" value="regiao" />',
            ['opcoes' => ['geral' => 'Todos', 'regiao' => 'Região']]
        );

        $view->assertSee('data-eg-segmented', false)
            ->assertSee('data-autosubmit', false)
            ->assertSee('class="eg-seg-btn active"', false)
            ->assertSee('data-value="regiao"', false)
            ->assertSee('<input type="hidden" name="agrupamento" id="agrupamento" value="regiao">', false);
    }

    public function test_segmented_modo_links_renderiza_ancoras(): void
    {
        $view = $this->blade(
            '<x-ui.segmented :links="$abas" active="Por Momento" />',
            ['abas' => ['Por Momento' => '/r?tab=momento', 'Total Geral' => '/r?tab=total']]
        );

        $view->assertSee('<a href="/r?tab=momento" class="eg-seg-btn active"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('<a href="/r?tab=total" class="eg-seg-btn"', false)
            ->assertDontSee('data-eg-segmented', false)
            ->assertDontSee('type="hidden"', false);
    }

    public function test_stat_card_aplica_cor_icone_e_variante_pequena(): void
    {
        $view = $this->blade('<x-ui.stat-card color="green" icon="people-fill" label="Inscritos" value="1.234" small hint="no período" />');

        $view->assertSee('eg-stat-card eg-stat-card--green', false)
            ->assertSee('bi-people-fill', false)
            ->assertSee('eg-stat-value eg-stat-value--sm', false)
            ->assertSee('1.234')
            ->assertSee('Inscritos')
            ->assertSee('no período');
    }

    public function test_empty_state_renderiza_icone_titulo_e_texto(): void
    {
        $view = $this->blade('<x-ui.empty-state icon="search" muted compact title="Nada encontrado">Ajuste os filtros.</x-ui.empty-state>');

        $view->assertSee('eg-empty-state eg-empty-state--compact', false)
            ->assertSee('eg-empty-icon eg-empty-icon--muted', false)
            ->assertSee('bi-search', false)
            ->assertSee('<h5>Nada encontrado</h5>', false)
            ->assertSee('<p>Ajuste os filtros.</p>', false);
    }

    public function test_modal_header_roxo_com_botao_fechar_branco(): void
    {
        $view = $this->blade('<x-ui.modal-header title="Exportar" icon="download" title-id="modalExportLabel" />');

        $view->assertSee('class="modal-header eg-modal-header"', false)
            ->assertSee('id="modalExportLabel"', false)
            ->assertSee('bi-download', false)
            ->assertSee('Exportar')
            ->assertSee('btn-close btn-close-white', false);
    }

    public function test_modal_header_variante_danger(): void
    {
        $view = $this->blade('<x-ui.modal-header title="Excluir" danger />');

        $view->assertSee('eg-modal-header--danger', false);
    }
}
