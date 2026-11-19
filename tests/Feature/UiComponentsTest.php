<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
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

        $view->assertSee('class="eg-hero"', false)
            ->assertSee('<i class="bi bi-bar-chart me-1"></i>Relatórios', false)
            ->assertSee('<h1 class="eg-hero__title">Painel</h1>', false)
            ->assertSee('<p class="eg-hero__subtitle">Resumo geral</p>', false)
            ->assertSee('<div class="eg-hero__actions">', false)
            ->assertSee('Exportar');
    }

    public function test_page_hero_omite_blocos_vazios(): void
    {
        $view = $this->blade('<x-ui.page-hero title="Só título" />');

        $view->assertSee('<h1 class="eg-hero__title">Só título</h1>', false)
            ->assertDontSee('container', false)
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

    public function test_stat_card_aceita_slots_com_atributos_para_scripts(): void
    {
        $view = $this->blade(
            '<x-ui.stat-card color="green" value-id="kpi-abertas" value="3" label="Abertos" />
            <x-ui.stat-card icon="calendar">
                <x-slot:value data-total="eventos">-</x-slot>
                <x-slot:label data-total-label="eventos">Ações</x-slot>
                <x-slot:hint data-total-help="eventos">Com respostas</x-slot>
            </x-ui.stat-card>'
        );

        $view->assertSee('id="kpi-abertas"', false)
            ->assertSee('class="eg-stat-value" data-total="eventos"', false)
            ->assertSee('class="eg-stat-label" data-total-label="eventos"', false)
            ->assertSee('class="eg-stat-hint" data-total-help="eventos"', false);
    }

    public function test_modal_header_renderiza_subtitulo_com_atributos(): void
    {
        $view = $this->blade(
            '<x-ui.modal-header title="Agendar"><x-slot:subtitle id="modal-momento-titulo"></x-slot></x-ui.modal-header>'
        );

        $view->assertSee('class="eg-modal-header__subtitle" id="modal-momento-titulo"', false);
    }

    public function test_paginacao_padrao_renderiza_contador_pagina_ativa_e_navegacao(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 12), 39, 12, 2, ['path' => '/usuarios']);

        $view = $this->blade('<x-ui.pagination :paginator="$paginator" />', ['paginator' => $paginator]);

        $view->assertSee('class="eg-pagination-wrap"', false)
            ->assertSee('class="eg-pagination"', false)
            ->assertSeeInOrder(['<strong>13</strong>', '<strong>24</strong>', '<strong>39</strong>'], false)
            ->assertDontSee('container', false)
            ->assertSee('data-eg-page-input', false)
            ->assertSee('value="2" min="1" max="4"', false)
            ->assertSee('data-base-url="/usuarios?page=1" data-page-name="page"', false)
            ->assertSee('<span class="eg-pagination__total">4</span>', false)
            ->assertSee('href="/usuarios?page=1" rel="prev"', false)
            ->assertSee('href="/usuarios?page=3" rel="next"', false)
            ->assertSee('href="/usuarios?page=1" aria-label="Primeira página"', false)
            ->assertSee('href="/usuarios?page=4" aria-label="Última página"', false);
    }

    public function test_paginacao_desabilita_anterior_na_primeira_pagina_e_aceita_variante_empilhada(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 10), 25, 10, 1, ['path' => '/x']);

        $view = $this->blade('<x-ui.pagination :paginator="$paginator" stacked />', ['paginator' => $paginator]);

        $view->assertSee('eg-pagination-wrap eg-pagination-wrap--stacked', false)
            ->assertSee('class="eg-page eg-page--nav is-disabled" aria-disabled="true" aria-label="Primeira página"', false)
            ->assertSee('class="eg-page eg-page--nav is-disabled" aria-disabled="true" aria-label="Página anterior"', false)
            ->assertDontSee('rel="prev"', false);
    }

    public function test_paginacao_nao_renderiza_com_pagina_unica(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 5), 5, 10, 1, ['path' => '/x']);

        $view = $this->blade('<x-ui.pagination :paginator="$paginator" />', ['paginator' => $paginator]);

        $view->assertDontSee('eg-pagination', false);
    }

    public function test_paginacao_simples_usa_view_padrao_do_engaja(): void
    {
        $paginator = new Paginator(range(1, 10), 10, 2, ['path' => '/x']);
        $paginator->hasMorePagesWhen(true);

        $html = (string) $paginator->links();

        $this->assertStringContainsString('eg-pagination eg-pagination--simple', $html);
        $this->assertStringContainsString('href="/x?page=1" rel="prev"', $html);
        $this->assertStringContainsString('href="/x?page=3" rel="next"', $html);
    }

    public function test_data_table_com_paginator_desenha_rodape_dentro_da_moldura(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 12), 39, 12, 1, ['path' => '/usuarios']);

        $view = $this->blade(
            '<x-data-table id="grid-x" :columns="[]" :rows="[]" :pagination="false" :paginator="$paginator" />',
            ['paginator' => $paginator]
        );

        $view->assertSee('class="dt-frame dt-frame--bordered"', false)
            ->assertSee('class="ag-theme-quartz w-100 dt-no-border"', false)
            ->assertSeeInOrder(['id="grid-x"', 'class="dt-footer"', 'eg-pagination'], false);
    }

    public function test_data_table_sem_paginator_nao_cria_moldura(): void
    {
        $view = $this->blade('<x-data-table id="grid-y" :columns="[]" :rows="[]" />');

        $view->assertDontSee('dt-frame', false)
            ->assertDontSee('dt-footer', false);
    }

    public function test_table_frame_omite_rodape_com_pagina_unica_e_aceita_sem_borda(): void
    {
        $paginator = new LengthAwarePaginator(range(1, 3), 3, 10, 1, ['path' => '/x']);

        $view = $this->blade(
            '<x-ui.table-frame :paginator="$paginator" :bordered="false"><table class="table"></table></x-ui.table-frame>',
            ['paginator' => $paginator]
        );

        $view->assertSee('<div class="dt-frame">', false)
            ->assertSee('<table class="table"></table>', false)
            ->assertDontSee('dt-footer', false);
    }

    public function test_site_footer_renderiza_marca_parceiros_e_aceita_classes(): void
    {
        $view = $this->blade('<x-site-footer class="mt-5" />');

        $view->assertSee('<footer class="site-footer mt-5">', false)
            ->assertSee('Sistema de Gestão de Participação e Engajamento.')
            ->assertSeeInOrder(['Realização', 'Parceria'])
            ->assertSee(asset('images/ipf-white.png'), false)
            ->assertSee(asset('images/petrobras-white.png'), false)
            ->assertSee('CNPJ 04.950.603/0001-05');
    }

    public function test_layout_da_aplicacao_usa_o_rodape_institucional(): void
    {
        $view = $this->view('layouts.footer');

        $view->assertSee('class="site-footer mt-5"', false);
    }

    public function test_site_header_mostra_acesso_para_visitantes(): void
    {
        $view = $this->blade('<x-site-header />');

        $view->assertSee('<header class="site-header">', false)
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee(asset('images/engaja-favicon.png'), false);
    }

    public function test_login_e_cadastro_usam_header_institucional_e_cards_de_formulario(): void
    {
        foreach (['login', 'register'] as $rota) {
            $this->get(route($rota))
                ->assertOk()
                ->assertSee('class="site-header"', false)
                ->assertSee('eg-form-card', false)
                ->assertSee('aria-current="page"', false);
        }
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

    public function test_modal_header_variante_warning_e_sem_botao_fechar(): void
    {
        $view = $this->blade('<x-ui.modal-header warning title="Exclusão bloqueada" :dismissible="false" />');

        $view->assertSee('eg-modal-header--warning', false)
            ->assertDontSee('btn-close', false);
    }
}
