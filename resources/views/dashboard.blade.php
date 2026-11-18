@extends('layouts.app')

@section('content')
<style>
    .transition-icon {
        transition: transform 0.2s ease-in-out;
        display: inline-block;
    }
    [aria-expanded="true"] .transition-icon {
        transform: rotate(90deg);
    }
    .btn-engaja-outline {
        color: #421944;
        border-color: #421944;
    }
    .btn-engaja-outline:hover {
        background-color: #421944;
        color: #fff;
    }
</style>
<x-ui.page-hero contained
    eyebrow="Dashboards" eyebrow-icon="people"
    title="Inscrições, Presenças e Ausências"
    subtitle="Visual completo das ações pedagógicas com expansão de presenças e exportação em PDF."
    class="mb-0">
    <x-slot:actions>
        <a href="{{ route('dashboard') }}" class="eg-hero-btn"><i class="bi bi-grid me-1"></i> Hub de dashboards</a>
        <a href="{{ route('dashboards.bi') }}" class="eg-hero-btn"><i class="bi bi-bar-chart-line me-1"></i> Dashboard BI</a>
        <a href="{{ route('dashboards.avaliacoes') }}" class="eg-hero-btn"><i class="bi bi-chat-square-text me-1"></i> Dashboard de respostas</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="container py-4">
    <x-ui.filter-card>
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="eg-filter-label">Ação pedagógica (evento)</label>
                <x-ui.select name="evento_id">
                    <option value="">Todas</option>
                    @foreach($eventos as $id => $nome)
                    <option value="{{ $id }}" @selected(request('evento_id')==$id)>{{ $nome }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="col-md-2">
                <label class="eg-filter-label">De</label>
                <x-ui.input type="date" name="de" value="{{ request('de') }}" />
            </div>

            <div class="col-md-2">
                <label class="eg-filter-label">Até</label>
                <x-ui.input type="date" name="ate" value="{{ request('ate') }}" />
            </div>

            <div class="col-md-3">
                <label class="eg-filter-label">Buscar (momento/ação)</label>
                <x-ui.input type="text" name="q" value="{{ request('q') }}" placeholder="Digite para filtrar..." />
            </div>

            <div class="col-md-2">
                <label class="eg-filter-label">Por página</label>
                <x-ui.select name="per_page" onchange="this.form.submit()">
                    <option value="25" @selected(request('per_page', 25)==25)>25</option>
                    <option value="50" @selected(request('per_page')==50)>50</option>
                    <option value="100" @selected(request('per_page')==100)>100</option>
                </x-ui.select>
            </div>

            {{-- mantém sort/dir atuais --}}
            <input type="hidden" name="sort" value="{{ request('sort', 'dia') }}">
            <input type="hidden" name="dir" value="{{ request('dir', 'desc') }}">

            <div class="col-md-auto eg-filter-actions">
                <button class="btn btn-engaja">Filtrar</button>
                <a href="{{ route('dashboards.presencas') }}" class="btn btn-outline-secondary">Limpar</a>
            </div>
        </form>
    </x-ui.filter-card>

    <div class="card shadow-sm">

        <div class="card-body p-0">
            {{-- barra de ações da tabela --}}
            <div class="d-flex justify-content-end gap-2 p-2 border-bottom bg-light">
                <button type="button" class="btn btn-outline-secondary btn-sm js-toggle-all" data-action="show">
                    Expandir todos
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm js-toggle-all" data-action="hide">
                    Recolher todos
                </button>
                <button type="button"
                    class="btn btn-outline-primary btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#exportPdfModal">
                    Baixar Listas (PDF)
                </button>
                <button type="button"
                    class="btn btn-outline-success btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#exportExcelModal">
                    <i class="fas fa-file-excel me-1"></i>Matriz de Presença (Excel)
                </button>
            </div>

            @php
                function sort_link($label,$key){
                    $curr = request('sort','dia');
                    $dir = request('dir','desc') === 'asc' ? 'asc' : 'desc';
                    $next = ($curr===$key && $dir==='asc') ? 'desc' : 'asc';
                    $params = array_merge(request()->except('page'), ['sort'=>$key,'dir'=>$next]);
                    $url = request()->url().'?'.http_build_query($params);
                    $is = $curr===$key;
                    $arrow = $is ? ($dir==='asc' ? '↑' : '↓') : '';
                    return '<a href="'.$url.'" class="text-decoration-none">'.$label.' <span class="text-muted">'.$arrow.'</span></a>';
                }

                $columns = [
                    ['field' => 'toggle', 'headerName' => '', 'width' => 44, 'resizable' => false, 'html' => true, 'align' => 'center'],
                    ['field' => 'data', 'headerHtml' => sort_link('Data', 'dia'), 'minWidth' => 110, 'flex' => 1],
                    ['field' => 'hora', 'headerHtml' => sort_link('Hora', 'hora'), 'minWidth' => 80, 'flex' => 1],
                    ['field' => 'momento', 'headerHtml' => sort_link('Momento', 'momento'), 'flex' => 2],
                    ['field' => 'municipio', 'headerHtml' => sort_link('Município', 'municipio'), 'flex' => 2],
                    ['field' => 'acao', 'headerHtml' => sort_link('Ação pedagógica', 'acao'), 'flex' => 2],
                    ['field' => 'inscritos', 'headerHtml' => sort_link('Inscritos', 'inscritos'), 'flex' => 1, 'align' => 'end'],
                    ['field' => 'presentes', 'headerHtml' => sort_link('Presentes', 'presentes'), 'flex' => 1, 'html' => true, 'align' => 'end'],
                    ['field' => 'ausentes', 'headerHtml' => sort_link('Ausentes', 'ausentes'), 'flex' => 1, 'html' => true, 'align' => 'end'],
                    ['field' => 'acoes', 'headerName' => 'Ações', 'width' => 130, 'html' => true, 'align' => 'center'],
                ];

                $rows = $atividades->map(function ($a) {
                    $data = \Carbon\Carbon::parse($a->dia)->format('d/m/Y');
                    $hora = \Illuminate\Support\Str::of($a->hora_inicio)->substr(0, 5);
                    $inscritosCount = $a->inscritos_count ?? 0;
                    $presentesCount = $a->presentes_count ?? 0;
                    $ausentesCount = max($inscritosCount - $presentesCount, 0);
                    $focusTarget = 'pres-' . $a->id . '-participantes';

                    $ausentesHtml = $ausentesCount > 0
                        ? '<a class="badge bg-warning text-dark text-decoration-none" href="#" data-toggle-detail="' . $a->id . '" data-focus-target="' . $focusTarget . '">' . $ausentesCount . '</a>'
                        : '<span class="text-muted">0</span>';

                    return [
                        'id' => $a->id,
                        'detailUrl' => route('dashboards.presencas.detalhes', $a->id),
                        'toggle' => '<button type="button" class="btn btn-sm btn-link text-muted p-0" data-toggle-detail="' . $a->id . '" aria-expanded="false"><i class="fas fa-chevron-right transition-icon"></i></button>',
                        'data' => $data,
                        'hora' => $hora,
                        'momento' => $a->descricao ?? 'Momento',
                        'municipio' => $a->municipio?->nome_com_estado ?? '-',
                        'acao' => $a->evento_nome ?? $a->evento->nome ?? '-',
                        'inscritos' => $inscritosCount,
                        'presentes' => '<a class="badge bg-success text-decoration-none" href="#" data-toggle-detail="' . $a->id . '" data-focus-target="' . $focusTarget . '">' . $presentesCount . '</a>',
                        'ausentes' => $ausentesHtml,
                        'acoes' => '<button type="button" class="btn btn-sm btn-engaja-outline" data-toggle-detail="' . $a->id . '"><i class="fas fa-users me-1"></i> Ver lista</button>',
                    ];
                })->values();
            @endphp

            <x-data-table
                id="grid-dashboard-presencas"
                :columns="$columns"
                :rows="$rows"
                :pagination="false"
                detail-row-field="_isDetailRow"
                :detail-row-height="420"
            />
        </div>

        @if($atividades->hasPages())
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <div class="small text-muted">
                Exibindo {{ $atividades->count() }} de {{ $atividades->total() }}
            </div>
            {{ $atividades->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>

<div class="modal fade" id="exportExcelModal" tabindex="-1" aria-labelledby="exportExcelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="GET" action="{{ route('dashboard.export.excel') }}">
                <x-ui.modal-header title-id="exportExcelModalLabel" title="Baixar Excel (Matriz de Frequência)" />
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label mb-1">Ação pedagógica <span class="text-danger">*</span></label>
                        <select name="evento_id" class="form-select" required>
                            <option value="">Selecione a ação...</option>
                            @foreach($eventos as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">O arquivo Excel será gerado em formato de Matriz de Presença agrupando as listas da Ação pedagógica selecionada por município.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="formato" value="docx" class="btn btn-outline-primary"><i class="bi bi-file-earmark-word"></i> Word</button>
                    <button type="submit" class="btn btn-success">Gerar Planilha</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="exportPdfModal" tabindex="-1" aria-labelledby="exportPdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="GET" action="{{ route('dashboard.export') }}" target="_blank">
                <x-ui.modal-header title-id="exportPdfModalLabel" title="Gerar PDF do dashboard" />
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label mb-1">Ação pedagógica</label>
                            <select name="pdf_evento_id" class="form-select form-select-sm">
                                <option value="">Todas</option>
                                @foreach($eventos as $id => $nome)
                                    <option value="{{ $id }}">{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1">Município</label>
                            <select name="pdf_municipio_id" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach(($municipios ?? collect()) as $municipio)
                                    <option value="{{ $municipio->id }}">{{ $municipio->nome_com_estado ?? $municipio->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label mb-1">Momento</label>
                            <select name="pdf_momento" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach(($momentos ?? collect()) as $momento)
                                    <option value="{{ $momento }}">{{ $momento }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">De</label>
                            <input type="date" name="pdf_de" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-1">Até</label>
                            <input type="date" name="pdf_ate" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <div class="d-flex gap-2">
                        <button type="reset" class="btn btn-light btn-sm">Limpar</button>
                        <button type="submit" class="btn btn-primary btn-sm">Gerar PDF</button>
                        <button type="submit" name="formato" value="docx" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-word"></i> Word</button>
                    </div>
                </div>
                <input type="hidden" name="sort" value="{{ request('sort', 'dia') }}">
                <input type="hidden" name="dir" value="{{ request('dir', 'desc') }}">
            </form>
        </div>
    </div>
</div>

{{-- Script para expandir/recolher e lazy loading de detalhes via linhas full-width do AG Grid --}}
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const gridEl = document.getElementById('grid-dashboard-presencas');
        if (!gridEl) return;

        const detailRowId = (atividadeId) => 'detail-' + atividadeId;
        const loadingHtml = '<div class="d-flex align-items-center justify-content-center gap-2 p-3 text-muted small"><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Carregando detalhes...</div>';
        const errorHtml = '<div class="p-3 text-danger small">Erro ao carregar detalhes. Tente expandir novamente.</div>';

        const setToggleExpanded = (api, atividadeId, expanded) => {
            const node = api.getRowNode(String(atividadeId));
            if (!node) return;
            node.data.toggle = '<button type="button" class="btn btn-sm btn-link text-muted p-0" data-toggle-detail="' + atividadeId + '" aria-expanded="' + expanded + '"><i class="fas fa-chevron-' + (expanded ? 'down' : 'right') + ' transition-icon"></i></button>';
            api.refreshCells({ force: true, rowNodes: [node], columns: ['toggle'] });
        };

        const collapseRow = (atividadeId) => {
            const api = gridEl._agGridApi;
            const node = api.getRowNode(detailRowId(atividadeId));
            if (!node) return;
            api.applyTransaction({ remove: [node.data] });
            setToggleExpanded(api, atividadeId, false);
        };

        // Mede a altura natural do conteúdo já renderizado e ajusta a altura
        // real da linha, respeitando o teto (data-detail-row-height) — acima
        // dele o .dt-detail-row passa a rolar internamente (overflow-y: auto).
        const fitRowHeight = (atividadeId) => {
            const api = gridEl._agGridApi;
            const node = api.getRowNode(detailRowId(atividadeId));
            if (!node) return;

            requestAnimationFrame(() => {
                const rowEl = gridEl.querySelector('[row-id="' + detailRowId(atividadeId) + '"] .dt-detail-row');
                if (!rowEl) return;
                const maxHeight = Number(gridEl.dataset.detailRowHeight || 420);
                node.setRowHeight(Math.min(rowEl.scrollHeight, maxHeight));
                api.onRowHeightChanged();
            });
        };

        const expandRow = (atividadeId, focusTarget) => {
            const api = gridEl._agGridApi;
            if (api.getRowNode(detailRowId(atividadeId))) return; // já expandida

            const masterNode = api.getRowNode(String(atividadeId));
            if (!masterNode) return;

            api.applyTransaction({
                add: [{ id: detailRowId(atividadeId), _isDetailRow: true, detailHtml: loadingHtml }],
                addIndex: masterNode.rowIndex + 1,
            });
            setToggleExpanded(api, atividadeId, true);

            fetch(masterNode.data.detailUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((r) => (r.ok ? r.text() : Promise.reject(r.status)))
                .then((html) => {
                    const node = api.getRowNode(detailRowId(atividadeId));
                    if (!node) return; // foi recolhida antes do fetch terminar
                    node.updateData({ ...node.data, detailHtml: html });
                    api.redrawRows({ rowNodes: [node] });
                    fitRowHeight(atividadeId);

                    if (focusTarget) {
                        requestAnimationFrame(() => {
                            const focusEl = gridEl.querySelector('#' + focusTarget);
                            if (focusEl) focusEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    }
                })
                .catch(() => {
                    const node = api.getRowNode(detailRowId(atividadeId));
                    if (!node) return;
                    node.updateData({ ...node.data, detailHtml: errorHtml });
                    api.redrawRows({ rowNodes: [node] });
                    fitRowHeight(atividadeId);
                });
        };

        const toggleRow = (atividadeId, focusTarget) => {
            const api = gridEl._agGridApi;
            if (api.getRowNode(detailRowId(atividadeId))) {
                collapseRow(atividadeId);
            } else {
                expandRow(atividadeId, focusTarget);
            }
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-toggle-detail]');
            if (!trigger || !gridEl.contains(trigger)) return;
            event.preventDefault();
            toggleRow(trigger.dataset.toggleDetail, trigger.dataset.focusTarget || null);
        });

        document.querySelectorAll('.js-toggle-all').forEach((btn) => {
            btn.addEventListener('click', () => {
                const api = gridEl._agGridApi;
                const masterIds = [];
                api.forEachNode((node) => {
                    if (!node.data._isDetailRow) masterIds.push(node.data.id);
                });
                masterIds.forEach((id) => (btn.dataset.action === 'show' ? expandRow(id) : collapseRow(id)));
            });
        });
    });
</script>

@endsection
