@extends('layouts.app')

@section('content')
    <x-ui.page-hero eyebrow="Operações" eyebrow-icon="calendar-event" title="Ações pedagógicas">
        <x-slot:actions>
            @hasanyrole('administrador|gerente')
            <button type="button" class="eg-hero-btn" id="btn-emitir-certificados" data-bs-toggle="modal" data-bs-target="#modalEmitirCertificados" disabled>Emitir certificados</button>
            @endhasanyrole
            @hasanyrole('administrador|gerente|eq_pedagogica')
            <a href="{{ route('eventos.create') }}" class="eg-hero-btn eg-hero-btn--solid">Nova ação pedagógica</a>
            @endhasanyrole
        </x-slot:actions>
    </x-ui.page-hero>

    <div>
        {{-- Filtros / busca --}}
        <x-ui.filter-card>
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <x-ui.input type="text" name="q" value="{{ request('q') }}" placeholder="Buscar por nome, tipo, objetivo" />
                </div>
                <div class="col-md-3">
                    <x-ui.select name="acao_geral">
                        <option value="">Todas as Ações Gerais</option>
                        @foreach(\App\Models\Evento::ACOES_GERAIS as $key => $label)
                        <option value="{{ $key }}" @selected(request('acao_geral') == $key)>
                            Ação Geral {{ $key }}
                        </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="col-md-3">
                    <x-ui.input type="date" name="de" value="{{ request('de') }}" placeholder="de" />
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-engaja">Filtrar</button>
                </div>
            </form>
        </x-ui.filter-card>

        @php
            if (!function_exists('evento_sort_link')) {
                function evento_sort_link($label, $key) {
                    $currentSort = request('sort', 'id');
                    $currentDir = strtolower((string) request('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
                    $nextDir = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';
                    $params = array_merge(request()->except('page'), ['sort' => $key, 'dir' => $nextDir]);
                    $url = request()->url() . '?' . http_build_query($params);
                    $isActive = $currentSort === $key;
                    $arrow = $isActive ? ($currentDir === 'asc' ? '↑' : '↓') : '';

                    return '<a href="' . $url . '" class="text-decoration-none text-nowrap">' . e($label) . ' <span class="text-muted">' . $arrow . '</span></a>';
                }
            }

            $columns = [
                ['field' => 'nome', 'headerHtml' => evento_sort_link('Nome', 'nome'), 'flex' => 1, 'minWidth' => 300, 'html' => true],
                ['field' => 'periodo', 'headerHtml' => evento_sort_link('Período', 'periodo'), 'width' => 90, 'minWidth' => 75, 'html' => true],
                ['field' => 'criado_por', 'headerHtml' => evento_sort_link('Criado por', 'criado_por'), 'width' => 90, 'minWidth' => 75],
                ['field' => 'acoes', 'headerName' => 'Ações', 'width' => 95, 'minWidth' => 85, 'html' => true, 'overflow' => 'visible'],
            ];

            $rows = $eventos->map(function ($ev) {
                $checklistSalvo = $ev->checklist_planejamento;
                $checklistsMarcados = is_array($checklistSalvo) ? count($checklistSalvo) : null;
                $isPlanejamentoIncompleto = $checklistsMarcados !== null && $checklistsMarcados < 13;

                $nomeHtml = '<div style="min-width: 0; width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="' . e($ev->nome) . '">'
                    . '<span class="fw-semibold text-truncate d-block mw-100" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . e($ev->nome) . '</span>';
                if ($isPlanejamentoIncompleto) {
                    $nomeHtml .= '<a href="' . route('eventos.edit', $ev) . '#checklist" class="badge bg-warning text-dark border-0 fw-normal text-decoration-none text-truncate d-inline-block mw-100" style="font-size: 0.75rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="Clique para retomar e concluir o checklist de planejamento (' . $checklistsMarcados . '/13 itens marcados).">⚠️ Planejamento incompleto</a>';
                }
                $nomeHtml .= '</div>';

                $inicio = $ev->data_inicio ? \Carbon\Carbon::parse($ev->data_inicio)->format('d/m/Y') : null;
                $fim = $ev->data_fim ? \Carbon\Carbon::parse($ev->data_fim)->format('d/m/Y') : null;
                $mostrarFim = $fim && (!$inicio || $fim !== $inicio);
                if ($inicio || $fim) {
                    $periodoHtml = e($inicio ?? '-') . ($mostrarFim ? '<br><span style="color: inherit; padding-left: 0.25rem;">até ' . e($fim) . '</span>' : '');
                } else {
                    $periodoHtml = '-';
                }

                $acoesHtml = '<div class="dropdown">'
                    . '<button class="btn btn-sm btn-engaja dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">Gerenciar</button>'
                    . '<ul class="dropdown-menu dropdown-menu-end">'
                    . '<li><a class="dropdown-item" href="' . route('eventos.show', $ev) . '">Ver</a></li>'
                    . '<li><a class="dropdown-item" href="' . route('eventos.planejamento.pdf', $ev) . '" target="_blank" rel="noopener noreferrer">Ver planejamento da ação</a></li>';

                if (auth()->user()->can('update', $ev) && auth()->user()->hasAnyRole(['administrador', 'gerente', 'eq_pedagogica'])) {
                    $acoesHtml .= '<li><a class="dropdown-item" href="' . route('eventos.edit', $ev) . '">Editar</a></li>';

                    $acoesHtml .= '<li><hr class="dropdown-divider"></li>'
                        . '<li>'
                        . '<form method="POST" action="' . route('eventos.duplicate', $ev) . '" data-confirm="Deseja duplicar esta ação pedagógica? Uma cópia será criada com os mesmos dados de edição e momentos, sem inscritos e presenças.">'
                        . csrf_field()
                        . '<button type="submit" class="dropdown-item">'
                        . ' Duplicar ação'
                        . '</button>'
                        . '</form>'
                        . '</li>';

                    if (auth()->user()->hasRole('administrador')) {
                        $acoesHtml .= '<li><hr class="dropdown-divider"></li>'
                            . '<li>'
                            . '<form method="POST" action="' . route('eventos.destroy', $ev) . '" data-confirm="Tem certeza que deseja excluir esta ação pedagógica?">'
                            . csrf_field() . method_field('DELETE')
                            . '<button type="submit" class="dropdown-item text-danger">Excluir</button>'
                            . '</form>'
                            . '</li>';
                    }
                }

                $acoesHtml .= '</ul></div>';

                return [
                    'id' => $ev->id,
                    'nome' => $nomeHtml,
                    'periodo' => $periodoHtml,
                    'criado_por' => $ev->user->name ?? '-',
                    'acoes' => $acoesHtml,
                ];
            })->values();
        @endphp

        <div class="card shadow-sm">
            <x-data-table
                id="grid-eventos"
                :columns="$columns"
                :rows="$rows"
                :pagination="false"
                row-selection="multiple"
            :paginator="$eventos->withQueryString()"
        />
        </div>
    </div>

    @hasanyrole('administrador|gerente')
    <form method="POST" action="{{ route('certificados.emitir.preparar') }}">
        @csrf
        <input type="hidden" name="eventos" id="eventosSelecionados">
        <div class="modal fade" id="modalEmitirCertificados" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <x-ui.modal-header title="Emitir certificados" />
                    <div class="modal-body">
                        <p class="text-muted mb-2">Selecione o modelo que será usado para emitir os certificados das ações selecionadas.</p>
                        <div class="mb-3">
                            <label class="form-label" for="modelo_id">Modelo de certificado</label>
                            <select name="modelo_id" id="modelo_id" class="form-select" required>
                                <option value="">-- Escolha um modelo --</option>
                                @foreach($modelosCertificados as $modelo)
                                    <option value="{{ $modelo->id }}">{{ $modelo->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check form-switch mt-3 mb-2 p-3 bg-light border rounded">
                            <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" name="unificar" id="unificarSwitch" value="1" style="transform: scale(1.2);">
                            <label class="form-check-label fw-bold" for="unificarSwitch">Unificar ações para a certificação</label>
                            <div class="text-muted small mt-1">Se ativada, o sistema juntará todos os eventos selecionados em um único certificado por pessoa (somando a carga horária).</div>
                        </div>
                        <div class="alert alert-info small mb-0">
                            Serão substituídas as tags: <code>%participante%</code> (nome do participante) e <code>%acao%</code> (nome da ação pedagógica).
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-outline-primary" id="btn-preview-certificado" disabled>Pré-visualizar</button>
                        <button type="submit" class="btn btn-engaja" id="btn-confirmar-emissao" disabled>Prosseguir</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    @endhasanyrole

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const STORAGE_KEY = 'eventos_selecionados_certificacao';

            //se a URL não tem parametros, eh porque o usuario clicou no menu. eh limpada a sessao para começar do zero.
            if (!window.location.search) {
                sessionStorage.removeItem(STORAGE_KEY);
            }

            let selectedSet = new Set(JSON.parse(sessionStorage.getItem(STORAGE_KEY)) || []);

            const gridEl = document.getElementById('grid-eventos');
            const inputEventos = document.getElementById('eventosSelecionados');
            const btnEmitir = document.getElementById('btn-emitir-certificados');
            const btnConfirmar = document.getElementById('btn-confirmar-emissao');
            const btnPreview = document.getElementById('btn-preview-certificado');
            const selectModelo = document.getElementById('modelo_id');

            //atualiza todos os botoes e inputs baseado na memoria
            const syncGlobalState = () => {

                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(selectedSet)));

                if (inputEventos) inputEventos.value = Array.from(selectedSet).join(',');

                //habilita/desabilita botao de emitir
                const hasSel = selectedSet.size > 0;
                if (btnEmitir) btnEmitir.disabled = !hasSel;

                //habilita/desabilita botões dentro do modal
                const hasModelo = selectModelo && selectModelo.value;
                const enableModalBtns = hasSel && hasModelo;
                if (btnConfirmar) btnConfirmar.disabled = !enableModalBtns;
                if (btnPreview) btnPreview.disabled = !enableModalBtns;
            };

            //o grid eh recriado a cada carregamento de pagina (paginacao server-side),
            //entao so guardamos os ids desta pagina para sincronizar com a selecao global
            const setupGridSelection = () => {
                const api = gridEl._agGridApi;
                if (!api) return;

                const pageIds = [];
                api.forEachNode((node) => {
                    const id = String(node.data.id);
                    pageIds.push(id);
                    if (selectedSet.has(id)) {
                        node.setSelected(true);
                    }
                });

                syncGlobalState();

                gridEl.addEventListener('datatable:selection-changed', (event) => {
                    const selectedNow = new Set(event.detail.rows.map((row) => String(row.id)));
                    pageIds.forEach((id) => {
                        if (selectedNow.has(id)) {
                            selectedSet.add(id);
                        } else {
                            selectedSet.delete(id);
                        }
                    });
                    syncGlobalState();
                });
            };

            if (gridEl) {
                if (gridEl.dataset.agGridInitialized === 'true') {
                    setupGridSelection();
                } else {
                    gridEl.addEventListener('datatable:ready', setupGridSelection, { once: true });
                }
            }

            //eventos do modal
            if (selectModelo) {
                selectModelo.addEventListener('change', syncGlobalState);
            }

            if (btnPreview) {
                btnPreview.addEventListener('click', () => {
                    if (!selectModelo.value || selectedSet.size === 0) return;
                    const params = new URLSearchParams({
                        modelo_id: selectModelo.value,
                        eventos: Array.from(selectedSet).join(','),
                    });
                    window.open(`{{ route('certificados.preview') }}?${params.toString()}`, '_blank');
                });
            }

            const modalEl = document.getElementById('modalEmitirCertificados');
            if (btnEmitir && modalEl) {
                btnEmitir.addEventListener('click', (e) => {
                    if (btnEmitir.disabled) {
                        e.preventDefault();
                        return;
                    }
                    const modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                    modalInstance.show();
                });
            }

            //roda a sincronizacao uma vez ao carregar para garantir que o estado inicie perfeito
            syncGlobalState();
        });
    </script>

@endpush
@endsection
