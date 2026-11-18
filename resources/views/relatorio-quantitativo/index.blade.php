@extends('layouts.app')

@section('content')
{{-- Flatpickr CSS --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

@php
    // Só os filtros compartilhados pelas duas abas viajam na troca de aba.
    // Assim o Total Geral não "herda" município/momento/turno da aba Por
    // Momento (que ele ignora), evitando que o export anuncie um filtro
    // que não foi aplicado.
    $abaQuery = array_intersect_key(request()->query(), array_flip(['evento_id', 'regiao_id', 'de', 'ate']));

    // Escopo do export do Total Geral: filtros aplicados + ordenação + dimensões.
    $tgExportQuery = array_intersect_key(request()->query(), array_flip(['evento_id', 'regiao_id', 'de', 'ate', 'sort', 'dir', 'dimensoes']));
@endphp

<x-ui.page-hero contained
    eyebrow="Relatórios" eyebrow-icon="table"
    title="Quantidade de participação e avaliação por encontro"
    subtitle="Presenças e avaliações por encontro, com totais por ação pedagógica."
    class="mb-0">
    <x-slot:actions>
        @if($tab === 'momento')
            <a href="{{ route('relatorio-quantitativo.exportar-momento') }}?{{ http_build_query(array_merge(request()->query(), ['formato' => 'pdf'])) }}" class="eg-hero-btn" title="Exportar como PDF">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
            </a>
            <a href="{{ route('relatorio-quantitativo.exportar-momento') }}?{{ http_build_query(array_merge(request()->query(), ['formato' => 'xlsx'])) }}" class="eg-hero-btn" title="Exportar como Excel">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel
            </a>
            <a href="{{ route('relatorio-quantitativo.exportar-momento') }}?{{ http_build_query(array_merge(request()->query(), ['formato' => 'docx'])) }}" class="eg-hero-btn" title="Exportar como Word">
                <i class="bi bi-file-earmark-word me-1"></i> Word
            </a>
        @else
            <a id="btn-export-total-geral-pdf" href="{{ route('relatorio-quantitativo.exportar-total-geral') }}?{{ http_build_query(array_merge($tgExportQuery, ['formato' => 'pdf'])) }}" class="eg-hero-btn" title="Exportar como PDF">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
            </a>
            <a id="btn-export-total-geral-xlsx" href="{{ route('relatorio-quantitativo.exportar-total-geral') }}?{{ http_build_query(array_merge($tgExportQuery, ['formato' => 'xlsx'])) }}" class="eg-hero-btn" title="Exportar como Excel">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel
            </a>
            <a id="btn-export-total-geral-docx" href="{{ route('relatorio-quantitativo.exportar-total-geral') }}?{{ http_build_query(array_merge($tgExportQuery, ['formato' => 'docx'])) }}" class="eg-hero-btn" title="Exportar como Word">
                <i class="bi bi-file-earmark-word me-1"></i> Word
            </a>
        @endif
    </x-slot:actions>
</x-ui.page-hero>

<div class="container py-4">

    {{-- Abas --}}
    <x-ui.segmented auto class="mb-4"
        :links="[
            'Relatório por Momento' => route('relatorio-quantitativo.index').'?'.http_build_query(array_merge($abaQuery, ['tab' => 'momento'])),
            'Total Geral de Participantes' => route('relatorio-quantitativo.index').'?'.http_build_query(array_merge($abaQuery, ['tab' => 'total-geral'])),
        ]"
        :active="$tab === 'momento' ? 'Relatório por Momento' : 'Total Geral de Participantes'" />

    {{-- Tabela Momento --}}
    @if($tab === 'momento')
    {{-- Filtros Aba Momento --}}
    <x-ui.filter-card>
            <form method="GET" action="{{ route('relatorio-quantitativo.index') }}" class="row g-3 align-items-end">

                <div class="col-md-3 col-lg-2">
                    <label class="eg-filter-label">Ação</label>
                    <x-ui.select name="evento_id" id="filter-evento-momento">
                        <option value="">Todas</option>
                        @foreach($eventos as $id => $nome)
                            <option value="{{ $id }}" @selected(request('evento_id') == $id)>{{ $nome }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="col-md-3 col-lg-2">
                    <label class="eg-filter-label">Região</label>
                    <x-ui.select name="regiao_id" id="filter-regiao-momento">
                        <option value="">Todas</option>
                        @foreach($regioes as $regiao)
                            <option value="{{ $regiao->id }}" @selected(request('regiao_id') == $regiao->id)>{{ $regiao->nome }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="col-md-3 col-lg-2">
                    <label class="eg-filter-label">Momento</label>
                    <x-ui.select name="descricao" id="filter-momento">
                        <option value="">Todos</option>
                        @foreach($momentos as $m)
                            <option value="{{ $m }}" @selected(request('descricao') == $m)>{{ $m }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="col-md-3 col-lg-2">
                    <label class="eg-filter-label">Município</label>
                    <x-ui.select name="municipio_id" id="filter-municipio-momento">
                        <option value="">Todos</option>
                        @foreach($municipios as $municipio)
                            <option value="{{ $municipio->id }}" @selected(request('municipio_id') == $municipio->id)>
                                {{ $municipio->nome_com_estado }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="col-md-3 col-lg-2">
                    <label class="eg-filter-label">Intervalo</label>
                    <x-ui.input type="text" id="filter-daterange-momento" placeholder="De ... até" />
                    <input type="hidden" name="de" id="filter-de-momento">
                    <input type="hidden" name="ate" id="filter-ate-momento">
                </div>

                <div class="col-md-2 col-lg-2">
                    <label class="eg-filter-label">Período</label>
                    <x-ui.select name="periodo">
                        <option value="">Todos</option>
                        <option value="manha" @selected(request('periodo') == 'manha')>Manhã</option>
                        <option value="tarde" @selected(request('periodo') == 'tarde')>Tarde</option>
                        <option value="noite" @selected(request('periodo') == 'noite')>Noite</option>
                    </x-ui.select>
                </div>

                <input type="hidden" name="tab" value="momento">
                <input type="hidden" name="sort" value="{{ request('sort', 'dia') }}">
                <input type="hidden" name="dir"  value="{{ request('dir', 'asc') }}">

                <div class="col-auto eg-filter-actions">
                    <button type="submit" class="btn btn-engaja">Filtrar</button>
                    <a href="{{ route('relatorio-quantitativo.index') }}?tab=momento" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
    </x-ui.filter-card>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @php
                if (! function_exists('rq_sort_link')) {
                function rq_sort_link(string $label, string $key): string {
                    $curr   = request('sort', 'dia');
                    $curDir = request('dir', 'asc') === 'asc' ? 'asc' : 'desc';
                    $next   = ($curr === $key && $curDir === 'asc') ? 'desc' : 'asc';
                    $params = array_merge(request()->except('page'), ['sort' => $key, 'dir' => $next]);
                    $url    = request()->url() . '?' . http_build_query($params);
                    $arrow  = ($curr === $key) ? ($curDir === 'asc' ? ' ↑' : ' ↓') : '';
                    return '<a href="' . e($url) . '" class="text-decoration-none text-dark">'
                         . e($label)
                         . '<span class="text-muted small">' . $arrow . '</span></a>';
                }
                }
            @endphp

            @if($atividades->isEmpty())
                <x-ui.empty-state compact muted icon="search" title="Nenhum encontro encontrado com os filtros aplicados.">Ajuste os filtros acima para ampliar a busca.</x-ui.empty-state>
            @else
                @php
                    $columns = [
                        ['field' => 'acao', 'headerHtml' => rq_sort_link('Ação', 'acao'), 'flex' => 2, 'colSpanWhen' => 'dt-row-subtotal', 'colSpanCount' => 5],
                        ['field' => 'momento', 'headerHtml' => rq_sort_link('Momento', 'momento'), 'flex' => 2],
                        ['field' => 'municipio', 'headerHtml' => rq_sort_link('Município', 'municipio'), 'flex' => 2],
                        ['field' => 'dia', 'headerHtml' => rq_sort_link('Data', 'dia'), 'flex' => 1],
                        ['field' => 'periodo', 'headerHtml' => rq_sort_link('Período', 'periodo'), 'flex' => 1],
                        ['field' => 'previstas', 'headerHtml' => rq_sort_link('Qtd Previstas', 'previstas'), 'flex' => 1],
                        ['field' => 'presentes', 'headerHtml' => rq_sort_link('Qtd Presentes', 'presentes'), 'flex' => 1],
                        ['field' => 'prop_presentes', 'headerName' => 'Presentes / Previstas', 'flex' => 1],
                        ['field' => 'avaliacoes', 'headerHtml' => rq_sort_link('Qtd Avaliações', 'avaliacoes'), 'flex' => 1],
                        ['field' => 'prop_avaliacoes', 'headerName' => 'Avaliações / Presentes', 'flex' => 1],
                    ];

                    $rows = [];

                    foreach ($atividades->groupBy('evento_nome') as $nomeAcao => $grupo) {
                        foreach ($grupo as $a) {
                            $horaStr = substr($a->hora_inicio ?? '', 0, 5);
                            $hora = (int) substr($horaStr, 0, 2);
                            $periodoLabel = $hora < 12 ? 'Manhã' : ($hora < 18 ? 'Tarde' : 'Noite');

                            $previstas = (int) $a->publico_esperado;
                            $presentes = (int) $a->presentes_count;
                            $avaliacoes = (int) $a->avaliacoes_count;

                            $propPres = $previstas > 0 ? round($presentes / $previstas * 100, 1) : 0;
                            $propAval = $presentes > 0 ? round($avaliacoes / $presentes * 100, 1) : 0;

                            $rows[] = [
                                'acao' => $a->evento_nome ?? '—',
                                'momento' => $a->descricao ?? '—',
                                'municipio' => $a->municipio_nome ?? '—',
                                'dia' => $a->dia ? \Carbon\Carbon::parse($a->dia)->format('d/m/Y') : '—',
                                'periodo' => $horaStr ? $periodoLabel . ' (' . $horaStr . ')' : '—',
                                'previstas' => $previstas ?: '—',
                                'presentes' => $presentes,
                                'prop_presentes' => $previstas > 0 ? $propPres . '%' : '—',
                                'avaliacoes' => $avaliacoes,
                                'prop_avaliacoes' => $presentes > 0 ? $propAval . '%' : '—',
                            ];
                        }

                        $totalPrevistas = $grupo->sum('publico_esperado');
                        $totalPresentes = $grupo->sum('presentes_count');
                        $totalAvaliacoes = $grupo->sum('avaliacoes_count');
                        $propTotPres = $totalPrevistas > 0 ? round($totalPresentes / $totalPrevistas * 100, 1) : 0;
                        $propTotAval = $totalPresentes > 0 ? round($totalAvaliacoes / $totalPresentes * 100, 1) : 0;

                        $rows[] = [
                            'acao' => 'Subtotal — ' . ($nomeAcao ?? 'Sem ação'),
                            'momento' => '',
                            'municipio' => '',
                            'dia' => '',
                            'periodo' => '',
                            'previstas' => $totalPrevistas ?: '—',
                            'presentes' => $totalPresentes,
                            'prop_presentes' => $totalPrevistas > 0 ? $propTotPres . '%' : '—',
                            'avaliacoes' => $totalAvaliacoes,
                            'prop_avaliacoes' => $totalPresentes > 0 ? $propTotAval . '%' : '—',
                            '_rowClass' => 'dt-row-subtotal',
                        ];
                    }
                @endphp

                <x-data-table
                    id="grid-relatorio-momento"
                    :columns="$columns"
                    :rows="$rows"
                    :pagination="false"
                    row-class-field="_rowClass"
                />
            @endif
        </div>
    </div>
    @endif

    {{-- Tabela Total Geral --}}
    @if($tab === 'total-geral')
    {{-- Filtros Aba Total Geral --}}
    <x-ui.filter-card>
            <form method="GET" action="{{ route('relatorio-quantitativo.index') }}" class="row g-3 align-items-end">

                <div class="col-md-3 col-lg-2">
                    <label class="eg-filter-label">Ação</label>
                    <x-ui.select name="evento_id" id="filter-evento-total">
                        <option value="">Todas</option>
                        @foreach($eventos as $id => $nome)
                            <option value="{{ $id }}" @selected(request('evento_id') == $id)>{{ $nome }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="col-md-3 col-lg-2">
                    <label class="eg-filter-label">Região</label>
                    <x-ui.select name="regiao_id" id="filter-regiao-total">
                        <option value="">Todas</option>
                        @foreach($regioes as $regiao)
                            <option value="{{ $regiao->id }}" @selected(request('regiao_id') == $regiao->id)>{{ $regiao->nome }}</option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="col-md-3 col-lg-2">
                    <label class="eg-filter-label">Intervalo</label>
                    <x-ui.input type="text" id="filter-daterange-total" placeholder="De ... até" />
                    <input type="hidden" name="de" id="filter-de-total">
                    <input type="hidden" name="ate" id="filter-ate-total">
                </div>

                <input type="hidden" name="tab" value="total-geral">
                <input type="hidden" name="sort" value="{{ request('sort', 'regiao') }}">
                <input type="hidden" name="dir"  value="{{ request('dir', 'asc') }}">

                <div class="col-auto eg-filter-actions">
                    <button type="submit" class="btn btn-engaja">Filtrar</button>
                    <a href="{{ route('relatorio-quantitativo.index') }}?tab=total-geral" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
    </x-ui.filter-card>

    {{-- Botões de alternância de dimensões --}}
    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
        <span class="text-muted small fw-semibold me-1">Exibir:</span>
        <button type="button" class="btn btn-sm btn-outline-secondary dim-toggle active" data-dim="cpf">CPF</button>
        <button type="button" class="btn btn-sm btn-outline-secondary dim-toggle" data-dim="raca_cor">Raça/Cor</button>
        <button type="button" class="btn btn-sm btn-outline-secondary dim-toggle" data-dim="genero">Gênero</button>
        <button type="button" class="btn btn-sm btn-outline-secondary dim-toggle" data-dim="pcd">PcD</button>
        <button type="button" class="btn btn-sm btn-outline-secondary dim-toggle" data-dim="certificados">Certificados</button>
        <button type="button" class="btn btn-sm btn-outline-secondary dim-toggle" data-dim="tag">Tag</button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @php
                if (! function_exists('tg_sort_link')) {
                function tg_sort_link(string $label, string $key): string {
                    $curr   = request('sort', 'regiao');
                    $curDir = request('dir', 'asc') === 'asc' ? 'asc' : 'desc';
                    $next   = ($curr === $key && $curDir === 'asc') ? 'desc' : 'asc';
                    $params = array_merge(request()->except('page'), ['sort' => $key, 'dir' => $next]);
                    $url    = request()->url() . '?' . http_build_query($params);
                    $arrow  = ($curr === $key) ? ($curDir === 'asc' ? ' ↑' : ' ↓') : '';
                    return '<a href="' . e($url) . '" class="text-decoration-none text-dark">'
                         . e($label)
                         . '<span class="text-muted small">' . $arrow . '</span></a>';
                }
                }
            @endphp

            @if($totalGeral->filter(fn($r) => !isset($r['_is_total']))->isEmpty())
                <x-ui.empty-state compact muted icon="search" title="Nenhum dado encontrado com os filtros aplicados.">Ajuste os filtros acima para ampliar a busca.</x-ui.empty-state>
            @else
                @php
                    $fmtPct = fn($v) => $v > 0 ? number_format($v, 1, ',', '.') . '%' : '—';

                    // Cada bloco vira um "column group" do AG Grid (CPF visível por
                    // padrão, os demais ficam ocultos e são ligados/desligados pelos
                    // botões .dim-toggle via api.setColumnsVisible()).
                    $columns = [
                        ['field' => 'regiao', 'headerHtml' => tg_sort_link('Região', 'regiao'), 'flex' => 1, 'colSpanWhen' => ['dt-row-subtotal', 'dt-row-unidentified'], 'colSpanCount' => 2],
                        ['field' => 'municipio', 'headerHtml' => tg_sort_link('Município', 'municipio'), 'flex' => 1],
                        ['field' => 'previstos', 'headerHtml' => tg_sort_link('Previstos', 'previstos'), 'flex' => 1],
                        ['field' => 'total_presentes', 'headerHtml' => tg_sort_link('Participantes distintos', 'total_presentes'), 'flex' => 1],
                        ['headerName' => 'CPF', 'groupId' => 'cpf', 'children' => [
                            ['field' => 'com_cpf', 'headerHtml' => tg_sort_link('Com CPF', 'com_cpf'), 'flex' => 1],
                            ['field' => 'sem_cpf', 'headerHtml' => tg_sort_link('Sem CPF', 'sem_cpf'), 'flex' => 1],
                            ['field' => 'pct_cpf', 'headerHtml' => tg_sort_link('% Com CPF', 'pct_cpf'), 'flex' => 1],
                        ]],
                        ['headerName' => 'Raça/Cor', 'groupId' => 'raca_cor', 'children' => [
                            ['field' => 'raca_branca', 'headerName' => 'Branca', 'flex' => 1, 'hide' => true],
                            ['field' => 'pct_raca_branca', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                            ['field' => 'raca_parda', 'headerName' => 'Parda', 'flex' => 1, 'hide' => true],
                            ['field' => 'pct_raca_parda', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                            ['field' => 'raca_preta', 'headerName' => 'Preta', 'flex' => 1, 'hide' => true],
                            ['field' => 'pct_raca_preta', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                            ['field' => 'raca_amarela', 'headerName' => 'Amarela', 'flex' => 1, 'hide' => true],
                            ['field' => 'pct_raca_amarela', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                            ['field' => 'raca_indigena', 'headerName' => 'Indígena', 'flex' => 1, 'hide' => true],
                            ['field' => 'pct_raca_indigena', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                        ]],
                        ['headerName' => 'Gênero', 'groupId' => 'genero', 'children' => [
                            ['field' => 'genero_mulheres', 'headerName' => 'Mulheres', 'flex' => 1, 'hide' => true],
                            ['field' => 'pct_genero_mulheres', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                            ['field' => 'genero_homens', 'headerName' => 'Homens', 'flex' => 1, 'hide' => true],
                            ['field' => 'pct_genero_homens', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                            ['field' => 'genero_outros', 'headerName' => 'Outros/NB', 'flex' => 1, 'hide' => true],
                            ['field' => 'pct_genero_outros', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                        ]],
                        ['headerName' => 'PcD', 'groupId' => 'pcd', 'children' => [
                            ['field' => 'pcd_n', 'headerName' => 'Qtd', 'flex' => 1, 'hide' => true],
                            ['field' => 'pcd_pct', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                        ]],
                        ['headerName' => 'Certificados', 'groupId' => 'certificados', 'children' => [
                            ['field' => 'certificados_n', 'headerName' => 'Qtd', 'flex' => 1, 'hide' => true],
                            ['field' => 'certificados_pct', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                        ]],
                        ['headerName' => 'Tag', 'groupId' => 'tag', 'children' => [
                            ['field' => 'tag_rede_ensino', 'headerName' => 'Rede Ensino', 'flex' => 1, 'hide' => true],
                            ['field' => 'pct_tag_rede_ensino', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                            ['field' => 'tag_movimento_social', 'headerName' => 'Mov. Social', 'flex' => 1, 'hide' => true],
                            ['field' => 'pct_tag_movimento_social', 'headerName' => '%', 'flex' => 1, 'hide' => true],
                        ]],
                    ];

                    $rows = $totalGeral->map(function ($row) use ($fmtPct) {
                        $tp = $row['metricas']['total_presentes'];

                        $rowClass = null;
                        if (isset($row['_is_total'])) {
                            $rowClass = 'dt-row-subtotal';
                        } elseif (isset($row['_is_unidentified']) || isset($row['_is_brasil'])) {
                            $rowClass = 'dt-row-unidentified';
                        }

                        return [
                            'regiao' => $row['regiao'],
                            'municipio' => $row['municipio_nome'],
                            'previstos' => $row['previstos'] ?: '—',
                            'total_presentes' => $tp ?: '—',
                            'com_cpf' => $row['metricas']['cpf']['com'],
                            'sem_cpf' => $row['metricas']['cpf']['sem'],
                            'pct_cpf' => $tp > 0 ? $fmtPct($row['metricas']['cpf']['pct']) : '—',
                            'raca_branca' => $row['metricas']['raca_cor']['branca'],
                            'pct_raca_branca' => $fmtPct($row['metricas']['raca_cor']['pct_branca']),
                            'raca_parda' => $row['metricas']['raca_cor']['parda'],
                            'pct_raca_parda' => $fmtPct($row['metricas']['raca_cor']['pct_parda']),
                            'raca_preta' => $row['metricas']['raca_cor']['preta'],
                            'pct_raca_preta' => $fmtPct($row['metricas']['raca_cor']['pct_preta']),
                            'raca_amarela' => $row['metricas']['raca_cor']['amarela'],
                            'pct_raca_amarela' => $fmtPct($row['metricas']['raca_cor']['pct_amarela']),
                            'raca_indigena' => $row['metricas']['raca_cor']['indigena'],
                            'pct_raca_indigena' => $fmtPct($row['metricas']['raca_cor']['pct_indigena']),
                            'genero_mulheres' => $row['metricas']['genero']['mulheres'],
                            'pct_genero_mulheres' => $fmtPct($row['metricas']['genero']['pct_mulheres']),
                            'genero_homens' => $row['metricas']['genero']['homens'],
                            'pct_genero_homens' => $fmtPct($row['metricas']['genero']['pct_homens']),
                            'genero_outros' => $row['metricas']['genero']['outros'],
                            'pct_genero_outros' => $fmtPct($row['metricas']['genero']['pct_outros']),
                            'pcd_n' => $row['metricas']['pcd']['n'],
                            'pcd_pct' => $fmtPct($row['metricas']['pcd']['pct']),
                            'certificados_n' => $row['metricas']['certificados']['n'],
                            'certificados_pct' => $fmtPct($row['metricas']['certificados']['pct']),
                            'tag_rede_ensino' => $row['metricas']['tag']['rede_ensino'],
                            'pct_tag_rede_ensino' => $fmtPct($row['metricas']['tag']['pct_rede_ensino']),
                            'tag_movimento_social' => $row['metricas']['tag']['movimento_social'],
                            'pct_tag_movimento_social' => $fmtPct($row['metricas']['tag']['pct_movimento_social']),
                            '_rowClass' => $rowClass,
                        ];
                    })->values();
                @endphp

                <x-data-table
                    id="grid-relatorio-total-geral"
                    :columns="$columns"
                    :rows="$rows"
                    :pagination="false"
                    row-class-field="_rowClass"
                />
                <p class="text-muted small mt-2 mb-0 px-3 pb-2">
                    <strong>Participantes distintos</strong> conta pessoas pelo <strong>município do participante</strong> (não pelo local do momento) — diferente de <em>Qtd Presentes</em> da aba "Por Momento", que conta registros de presença.
                    <strong>Previstos</strong> reflete o público esperado dos momentos (pelo município do momento), então pode não bater com os presentes de uma mesma linha.
                </p>
            @endif
        </div>
    </div>
    @endif

</div>

<script>
(function () {
    // Cascata de filtros apenas para a aba Momento
    var eventoSelect    = document.getElementById('filter-evento-momento');
    var momentoSelect   = document.getElementById('filter-momento');
    var municipioSelect = document.getElementById('filter-municipio-momento');

    if (!eventoSelect) return;

    var endpointBase      = '{{ route('relatorio-quantitativo.momentos') }}';
    var selectedMomento   = '{{ addslashes(request('descricao', '')) }}';
    var selectedMunicipio = '{{ request('municipio_id', '') }}';

    function rebuildStringSelect(selectEl, items, currentVal) {
        var first = selectEl.options[0];
        selectEl.innerHTML = '';
        selectEl.appendChild(first);
        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item;
            opt.text  = item;
            if (item === currentVal) opt.selected = true;
            selectEl.appendChild(opt);
        });
    }

    function rebuildObjectSelect(selectEl, items, currentVal) {
        var first = selectEl.options[0];
        selectEl.innerHTML = '';
        selectEl.appendChild(first);
        items.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.id;
            opt.text  = item.nome;
            if (String(item.id) === String(currentVal)) opt.selected = true;
            selectEl.appendChild(opt);
        });
    }

    eventoSelect.addEventListener('change', function () {
        var eventoId = this.value;
        var url      = endpointBase + (eventoId ? '?evento_id=' + encodeURIComponent(eventoId) : '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                rebuildStringSelect(momentoSelect,   data.momentos,   selectedMomento);
                rebuildObjectSelect(municipioSelect, data.municipios, selectedMunicipio);
            })
            .catch(function (err) {
                console.error('Erro ao carregar filtros:', err);
            });
    });
})();
</script>

{{-- Mutual exclusivity para aba Momento --}}
<script>
(function () {
    var municipioSelect = document.getElementById('filter-municipio-momento');
    var regiaoSelect = document.getElementById('filter-regiao-momento');

    if (!municipioSelect || !regiaoSelect) return;

    // Desmarcar região quando município for selecionado (aba Momento)
    municipioSelect.addEventListener('change', function () {
        if (this.value) {
            regiaoSelect.value = '';
        }
    });

    // Desmarcar município quando região for selecionada (aba Momento)
    regiaoSelect.addEventListener('change', function () {
        if (this.value) {
            municipioSelect.value = '';
        }
    });
})();
</script>

{{-- Script de alternância de dimensões na aba Total Geral: liga/desliga os
     column groups do AG Grid via api.setColumnsVisible() --}}
<script>
(function () {
    var toggleBtns = document.querySelectorAll('.dim-toggle');
    if (!toggleBtns.length) return;

    var dimColumns = {
        cpf: ['com_cpf', 'sem_cpf', 'pct_cpf'],
        raca_cor: ['raca_branca', 'pct_raca_branca', 'raca_parda', 'pct_raca_parda', 'raca_preta', 'pct_raca_preta', 'raca_amarela', 'pct_raca_amarela', 'raca_indigena', 'pct_raca_indigena'],
        genero: ['genero_mulheres', 'pct_genero_mulheres', 'genero_homens', 'pct_genero_homens', 'genero_outros', 'pct_genero_outros'],
        pcd: ['pcd_n', 'pcd_pct'],
        certificados: ['certificados_n', 'certificados_pct'],
        tag: ['tag_rede_ensino', 'pct_tag_rede_ensino', 'tag_movimento_social', 'pct_tag_movimento_social'],
    };

    function getGridApi() {
        var gridEl = document.getElementById('grid-relatorio-total-geral');
        return gridEl ? gridEl._agGridApi : null;
    }

    function updateExportUrls() {
        var activeDims = Array.from(document.querySelectorAll('.dim-toggle.active')).map(function (b) { return b.dataset.dim; });

        ['btn-export-total-geral-pdf', 'btn-export-total-geral-xlsx', 'btn-export-total-geral-docx'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (!btn) return;
            var url = new URL(btn.href);
            url.searchParams.delete('dimensoes[]');
            activeDims.forEach(function (d) { url.searchParams.append('dimensoes[]', d); });
            btn.href = url.toString();
        });
    }

    toggleBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dim = this.dataset.dim;
            var isActive = this.classList.toggle('active');
            var api = getGridApi();
            var colIds = dimColumns[dim] || [];

            if (api) {
                api.setColumnsVisible(colIds, isActive);
            }

            updateExportUrls();
        });
    });

    updateExportUrls();
})();
</script>

{{-- Flatpickr JS --}}
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/rangePlugin.js"></script>

<script>
(function () {
    // Formatar data para YYYY-MM-DD
    function formatDate(date) {
        return new Date(date).toISOString().split('T')[0];
    }

    // Função para inicializar Flatpickr para um formulário específico
    function initializeDatePicker(daterangeId, deInputId, ateInputId) {
        var daterangeInput = document.getElementById(daterangeId);
        var deInput = document.getElementById(deInputId);
        var ateInput = document.getElementById(ateInputId);

        if (!daterangeInput) return;

        flatpickr(daterangeInput, {
            mode: 'range',
            dateFormat: 'd/m/Y',
            locale: 'pt',
            placeholder: 'De ... até',
            defaultDate: [
                '{{ request('de') ? request('de') : '' }}',
                '{{ request('ate') ? request('ate') : '' }}'
            ],
            onChange: function (selectedDates) {
                if (selectedDates.length === 2) {
                    deInput.value = formatDate(selectedDates[0]);
                    ateInput.value = formatDate(selectedDates[1]);
                } else if (selectedDates.length === 1) {
                    deInput.value = formatDate(selectedDates[0]);
                    ateInput.value = '';
                }
            }
        });

        // Set initial values if already selected
        if ('{{ request('de') }}') {
            deInput.value = '{{ request('de') }}';
        }
        if ('{{ request('ate') }}') {
            ateInput.value = '{{ request('ate') }}';
        }
    }

    // Initialize date pickers para ambas as abas
    initializeDatePicker('filter-daterange-momento', 'filter-de-momento', 'filter-ate-momento');
    initializeDatePicker('filter-daterange-total', 'filter-de-total', 'filter-ate-total');
})();
</script>

@endsection
