@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Operações / Gerenciamento" eyebrow-icon="calendar2-check"
    title="Controle de Confirmação de Presenças"
    subtitle="Gerencie em tempo real a abertura, o fechamento e os agendamentos automáticos de presença em todos os momentos pedagógicos.">
    <x-slot:actions>
        <a href="{{ route('eventos.index') }}" class="eg-hero-btn">
            <i class="bi bi-arrow-left me-1"></i> Ações Pedagógicas
        </a>
        <a href="{{ route('dashboards.presencas') }}" class="eg-hero-btn">
            <i class="bi bi-bar-chart-fill me-1"></i> Dashboard de Presenças
        </a>
    </x-slot:actions>
</x-ui.page-hero>

<div>

    {{-- Cards de resumo rápido --}}
    <div class="eg-stats-row">
        <x-ui.stat-card color="purple" icon="collection-fill" label="Total de Momentos" :value="$totalAtividades" />
        <x-ui.stat-card color="green" icon="check-circle-fill" label="Total de Momentos Abertos" value-id="kpi-abertas" :value="$totalAbertas" />
        <x-ui.stat-card color="gray" icon="lock-fill" label="Total de Momentos Fechados" value-id="kpi-fechadas" :value="$totalFechadas" />
        <x-ui.stat-card color="blue" icon="clock-history" label="Com Agendamento Futuro" :value="$comAgendamentoFuturo" />
    </div>

    {{-- Barra de Filtros --}}
    <x-ui.filter-card>
        <form method="GET" action="{{ route('presencas.gerenciamento') }}" class="row g-3 align-items-end">

            {{-- Busca textual --}}
            <div class="col-12 col-lg-4">
                <label class="eg-filter-label">Buscar (momento ou ação)</label>
                <x-ui.input type="text" name="q" value="{{ request('q') }}" placeholder="Nome do momento ou ação..." />
            </div>

            {{-- Status de Presença --}}
            <div class="col-6 col-lg-2">
                <label class="eg-filter-label">Status da Presença</label>
                <x-ui.select name="status">
                    <option value="">Todos os status</option>
                    <option value="aberta" @selected(request('status') === 'aberta')>Aberta agora</option>
                    <option value="fechada" @selected(request('status') === 'fechada')>Fechada agora</option>
                    <option value="agendada" @selected(request('status') === 'agendada')>Com agendamento futuro</option>
                </x-ui.select>
            </div>

            {{-- Ação Pedagógica (Evento) --}}
            <div class="col-6 col-lg-3">
                <label class="eg-filter-label">Ação Pedagógica</label>
                <x-ui.select name="evento_id">
                    <option value="">Todas as ações</option>
                    @foreach($eventos as $id => $nome)
                        <option value="{{ $id }}" @selected(request('evento_id') == $id)>{{ $nome }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            {{-- Município --}}
            <div class="col-6 col-lg-3">
                <label class="eg-filter-label">Município</label>
                <x-ui.select name="municipio_id">
                    <option value="">Todos</option>
                    @foreach($municipios as $m)
                        <option value="{{ $m->id }}" @selected(request('municipio_id') == $m->id)>
                            {{ $m->nome }} ({{ $m->estado->sigla ?? '—' }})
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            {{-- Período (De) --}}
            <div class="col-6 col-md-3 col-lg-2">
                <label class="eg-filter-label">De</label>
                <x-ui.input type="date" name="de" value="{{ request('de') }}" />
            </div>

            {{-- Período (Até) --}}
            <div class="col-6 col-md-3 col-lg-2">
                <label class="eg-filter-label">Até</label>
                <x-ui.input type="date" name="ate" value="{{ request('ate') }}" />
            </div>

            {{-- Botões de ação --}}
            <div class="col eg-filter-actions justify-content-end">
                @if(request()->anyFilled(['q', 'status', 'evento_id', 'municipio_id', 'de', 'ate']))
                    <a href="{{ route('presencas.gerenciamento') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i> Limpar filtros
                    </a>
                @endif
                <button type="submit" class="btn btn-engaja px-3">
                    <i class="bi bi-funnel-fill me-1"></i> Filtrar
                </button>
            </div>
        </form>
    </x-ui.filter-card>

    {{-- Tabela de Dados (AG-Grid via <x-data-table>) --}}
    @if ($atividades->isEmpty())
        <x-ui.empty-state icon="calendar-x" muted title="Nenhum momento encontrado">
            Ajuste ou limpe os filtros para ver outros momentos.
        </x-ui.empty-state>
    @else
        @php
            $fuso = \App\Support\AgendamentoPresenca::FUSO;
            $curto = fn ($d) => $d->copy()->timezone($fuso)->format('d/m H:i');

            $columns = [
                [
                    'colId' => 'momento',
                    'field' => 'momento',
                    'headerName' => 'Momento / Ação Pedagógica',
                    'flex' => 2.5,
                    'minWidth' => 240,
                    'html' => true,
                ],
                [
                    'colId' => 'data_horario',
                    'field' => 'data_horario',
                    'headerName' => 'Data / Horário',
                    'flex' => 1,
                    'minWidth' => 135,
                    'html' => true,
                ],
                [
                    'colId' => 'presentes_inscritos',
                    'field' => 'presentes_inscritos',
                    'headerName' => 'Presentes / Inscritos',
                    'width' => 165,
                    'minWidth' => 165,
                    'maxWidth' => 180,
                    'flex' => 0,
                    'suppressSizeToFit' => true,
                    'align' => 'center',
                    'html' => true,
                ],
                [
                    'colId' => 'status_presenca',
                    'field' => 'status_presenca',
                    'headerName' => 'Presença',
                    'width' => 140,
                    'minWidth' => 135,
                    'maxWidth' => 150,
                    'flex' => 0,
                    'suppressSizeToFit' => true,
                    'align' => 'center',
                    'html' => true,
                ],
                [
                    'colId' => 'agendamento',
                    'field' => 'agendamento',
                    'headerName' => 'Agendamento',
                    'flex' => 1.5,
                    'minWidth' => 160,
                    'html' => true,
                ],
                [
                    'colId' => 'acoes',
                    'field' => 'acoes',
                    'headerName' => 'Ações',
                    'width' => 115,
                    'minWidth' => 110,
                    'maxWidth' => 125,
                    'flex' => 0,
                    'suppressSizeToFit' => true,
                    'sortable' => false,
                    'filter' => false,
                    'resizable' => false,
                    'align' => 'center',
                    'cellClass' => 'd-flex justify-content-center align-items-center text-center',
                    'html' => true,
                ],
            ];

            $rows = $atividades->map(function ($a) use ($curto) {
                $estaAberta = $a->presencaEstaAberta();

                // 1. Momento e Evento
                $urlMomento = route('atividades.show', $a);
                $momentoHtml = '<div class="w-100 overflow-hidden"><a href="' . e($urlMomento) . '" class="fw-bold text-engaja text-decoration-none d-block text-truncate" title="' . e($a->descricao) . '">'
                    . e($a->descricao ?: 'Momento #' . $a->id) . '</a>'
                    . '<div class="small text-muted text-truncate" title="' . e($a->evento->nome ?? '') . '">'
                    . e($a->evento->nome ?? '—') . '</div></div>';

                // 2. Data e Horário
                $diaFmt = $a->dia ? \Carbon\Carbon::parse($a->dia)->format('d/m/Y') : '—';
                $horaFmt = substr((string) $a->hora_inicio, 0, 5) . ($a->hora_fim ? ' – ' . substr((string) $a->hora_fim, 0, 5) : '');
                $diaHorarioHtml = '<div class="lh-sm"><div class="fw-semibold">' . e($diaFmt) . '</div><div class="small text-muted">' . e($horaFmt) . '</div></div>';

                // 3. Presentes / Inscritos em um único pill
                $inscritos = (int) $a->inscritos_count;
                $presentes = (int) $a->presentes_count;
                $presencasHtml = '<span class="badge rounded-pill bg-light text-dark border px-3 py-2" title="' . $presentes . ' presentes de ' . $inscritos . ' inscritos">'
                    . $presentes . ' / ' . $inscritos . '</span>';

                // 4. Presença: switch on/off assíncrono + estado
                $toggleUrl = route('atividades.presenca.toggle', $a);
                $statusHtml = '<div class="form-check form-switch d-flex align-items-center justify-content-center gap-2 m-0 p-0">'
                    . '<input type="checkbox" role="switch" class="form-check-input m-0 js-toggle-presenca" style="width:2.75em;height:1.45em;cursor:pointer;"'
                    . ' data-url="' . e($toggleUrl) . '"'
                    . ($estaAberta ? ' checked' : '')
                    . ' aria-label="Abrir ou fechar presença"'
                    . ' title="' . ($estaAberta ? 'Clique para fechar a presença agora' : 'Clique para abrir a presença agora') . '">'
                    . '<span class="js-toggle-label fw-semibold ' . ($estaAberta ? 'text-success' : 'text-secondary') . '" style="min-width:3.6em;">' . ($estaAberta ? 'Aberta' : 'Fechada') . '</span>'
                    . '</div>';

                // 5. Agendamento (formato enxuto)
                $temAgendamento = (bool) ($a->presenca_abre_em || $a->presenca_fecha_em);
                $valAbreInput = \App\Support\AgendamentoPresenca::paraInput($a->presenca_abre_em) ?? '';
                $valFechaInput = \App\Support\AgendamentoPresenca::paraInput($a->presenca_fecha_em) ?? '';

                $agendamentoParts = [];
                if ($a->presenca_abre_em) {
                    $agendamentoParts[] = '<div class="small text-nowrap"><i class="bi bi-unlock text-success me-1"></i><span class="text-muted">Abre</span> <strong>' . e($curto($a->presenca_abre_em)) . '</strong></div>';
                }
                if ($a->presenca_fecha_em) {
                    $agendamentoParts[] = '<div class="small text-nowrap"><i class="bi bi-lock text-danger me-1"></i><span class="text-muted">Fecha</span> <strong>' . e($curto($a->presenca_fecha_em)) . '</strong></div>';
                }
                $agendamentoHtml = empty($agendamentoParts)
                    ? '<span class="text-muted small">Sem agendamento</span>'
                    : '<div class="lh-sm">' . implode('', $agendamentoParts) . '</div>';

                // 6. Ações
                $agendamentoUrl = route('atividades.presenca.agendamento', $a);
                $limparAgendamentoUrl = route('atividades.presenca.agendamento.limpar', $a);
                $qrUrl = route('presenca.confirmar', $a);

                $svgClock = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/></svg>';

                $svgLink = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/></svg>';

                $btnAgendarClass = $temAgendamento
                    ? 'btn btn-sm btn-primary rounded-circle d-inline-flex align-items-center justify-content-center position-relative js-btn-agendar shadow-sm'
                    : 'btn btn-sm btn-outline-primary rounded-circle d-inline-flex align-items-center justify-content-center position-relative js-btn-agendar';

                $badgeAgendamentoAtivo = $temAgendamento
                    ? '<span class="position-absolute top-0 start-100 translate-middle p-1 bg-warning border border-light rounded-circle" title="Agendamento ativo"></span>'
                    : '';

                $acoesHtml = '<div class="d-inline-flex align-items-center justify-content-center gap-2">'
                    . '<button type="button" class="' . $btnAgendarClass . '" style="width:34px;height:34px;min-width:34px;padding:0;"'
                    . ' data-bs-toggle="modal" data-bs-target="#modalGerenciamentoAgendamento"'
                    . ' data-tt="Configurar agendamento automático"'
                    . ' data-id="' . $a->id . '"'
                    . ' data-action="' . e($agendamentoUrl) . '"'
                    . ' data-action-limpar="' . e($limparAgendamentoUrl) . '"'
                    . ' data-abre="' . e($valAbreInput) . '"'
                    . ' data-fecha="' . e($valFechaInput) . '"'
                    . ' data-descricao="' . e($a->descricao ?: 'Momento #' . $a->id) . '"'
                    . ' data-tem-agendamento="' . ($temAgendamento ? '1' : '0') . '"'
                    . ' title="Configurar agendamento automático" aria-label="Configurar agendamento automático">'
                    . $svgClock
                    . $badgeAgendamentoAtivo
                    . '</button>'
                    . '<a href="' . e($qrUrl) . '" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center" style="width:34px;height:34px;min-width:34px;padding:0;"'
                    . ' data-tt="Abrir página de presença" title="Abrir página de presença" aria-label="Abrir página de presença">'
                    . $svgLink
                    . '</a>'
                    . '</div>';

                return [
                    'id' => 'atv-' . $a->id,
                    'momento' => $momentoHtml,
                    'data_horario' => $diaHorarioHtml,
                    'presentes_inscritos' => $presencasHtml,
                    'status_presenca' => $statusHtml,
                    'agendamento' => $agendamentoHtml,
                    'acoes' => $acoesHtml,
                ];
            })->values();
        @endphp

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <x-data-table
                    id="grid-controle-presencas"
                    :columns="$columns"
                    :rows="$rows"
                    :pagination="false"
                    :row-height="72"
            :paginator="$atividades"
        />
            </div>
        </div>
    @endif

</div>

{{-- Modal Unificado de Agendamento de Presença --}}
<div class="modal fade" id="modalGerenciamentoAgendamento" tabindex="-1" aria-labelledby="modalGerenciamentoAgendamentoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">

            <x-ui.modal-header icon="clock-history" title="Agendar Confirmação de Presença" title-id="modalGerenciamentoAgendamentoLabel">
                <x-slot:subtitle id="modal-momento-titulo"></x-slot:subtitle>
            </x-ui.modal-header>

            <form action="" method="POST" id="form-gerenciamento-agendamento">
                @csrf
                @method('PATCH')
                <div class="modal-body py-3">
                    <p class="text-muted small mb-3">
                        Defina a data e a hora para abertura e/ou fechamento automático da presença neste momento (horário de Brasília).
                    </p>

                    <div class="mb-3">
                        <label for="modal_g_abre_em" class="form-label fw-semibold">Abrir presença em</label>
                        <input type="datetime-local" name="presenca_abre_em" id="modal_g_abre_em" class="form-control">
                        <div class="form-text">Deixe em branco se já estiver aberta ou se desejar abrir manualmente.</div>
                    </div>

                    <div class="mb-3">
                        <label for="modal_g_fecha_em" class="form-label fw-semibold">Fechar presença em</label>
                        <input type="datetime-local" name="presenca_fecha_em" id="modal_g_fecha_em" class="form-control">
                        <div class="form-text">Deixe em branco se desejar fechar manualmente.</div>
                    </div>
                </div>

                <div class="modal-footer border-top-0 pt-0 d-flex justify-content-between">
                    <div>
                        <button type="submit"
                                form="form-gerenciamento-limpar-agendamento"
                                id="btn-modal-g-limpar"
                                class="btn btn-outline-danger btn-sm"
                                style="display: none;"
                                onclick="return confirm('Tem certeza que deseja remover o agendamento deste momento?');">
                            Remover agendamento
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-engaja">Salvar agendamento</button>
                    </div>
                </div>
            </form>

            <form id="form-gerenciamento-limpar-agendamento" action="" method="POST" class="d-none">
                @csrf
                @method('DELETE')
            </form>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('modalGerenciamentoAgendamento');
    if (!modalEl) return;

    modalEl.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const action = button.getAttribute('data-action') || '';
        const actionLimpar = button.getAttribute('data-action-limpar') || '';
        const abre = button.getAttribute('data-abre') || '';
        const fecha = button.getAttribute('data-fecha') || '';
        const descricao = button.getAttribute('data-descricao') || '';
        const temAgendamento = button.getAttribute('data-tem-agendamento') === '1';

        const tituloEl = modalEl.querySelector('#modal-momento-titulo');
        if (tituloEl) tituloEl.textContent = descricao;

        const formAgendar = modalEl.querySelector('#form-gerenciamento-agendamento');
        if (formAgendar) formAgendar.setAttribute('action', action);

        const formLimpar = modalEl.querySelector('#form-gerenciamento-limpar-agendamento');
        if (formLimpar) formLimpar.setAttribute('action', actionLimpar);

        const inputAbre = modalEl.querySelector('#modal_g_abre_em');
        if (inputAbre) inputAbre.value = abre;

        const inputFecha = modalEl.querySelector('#modal_g_fecha_em');
        if (inputFecha) inputFecha.value = fecha;

        const btnLimpar = modalEl.querySelector('#btn-modal-g-limpar');
        if (btnLimpar) {
            btnLimpar.style.display = temAgendamento ? 'inline-block' : 'none';
        }
    });
});
// Switch assíncrono de abrir/fechar presença + tooltips dos botões de ação.
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('#form-gerenciamento-agendamento input[name="_token"]')?.value;

    function toast(msg, ok) {
        let box = document.getElementById('presenca-toast-box');
        if (!box) {
            box = document.createElement('div');
            box.id = 'presenca-toast-box';
            box.className = 'position-fixed bottom-0 end-0 p-3';
            box.style.zIndex = 2000;
            document.body.appendChild(box);
        }
        const el = document.createElement('div');
        el.className = 'alert alert-' + (ok ? 'success' : 'danger') + ' shadow py-2 px-3 mb-2 small';
        el.textContent = msg;
        box.appendChild(el);
        setTimeout(() => el.remove(), 3500);
    }

    function ajustarKpi(id, delta) {
        const el = document.getElementById(id);
        if (el) el.textContent = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
    }

    function pintar(input, aberta) {
        const label = input.closest('.form-switch')?.querySelector('.js-toggle-label');
        input.checked = aberta;
        input.title = aberta ? 'Clique para fechar a presença agora' : 'Clique para abrir a presença agora';
        if (label) {
            label.textContent = aberta ? 'Aberta' : 'Fechada';
            label.classList.toggle('text-success', aberta);
            label.classList.toggle('text-secondary', !aberta);
        }
    }

    document.addEventListener('change', async function (e) {
        const input = e.target.closest?.('.js-toggle-presenca');
        if (!input) return;

        const desejado = input.checked;
        input.disabled = true;
        try {
            const resp = await fetch(input.dataset.url, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const data = await resp.json();
            // Estado anterior exibido na tela era o oposto do desejado.
            if (data.aberta !== !desejado) {
                ajustarKpi('kpi-abertas', data.aberta ? 1 : -1);
                ajustarKpi('kpi-fechadas', data.aberta ? -1 : 1);
            }
            pintar(input, data.aberta);
            toast(data.message, true);
        } catch (err) {
            pintar(input, !desejado);
            toast('Não foi possível alterar a presença. Tente novamente.', false);
        } finally {
            input.disabled = false;
        }
    });

    // Tooltips Bootstrap (as células do AG Grid são criadas dinamicamente).
    document.addEventListener('mouseover', function (e) {
        const el = e.target.closest?.('[data-tt]');
        if (!el || !window.bootstrap?.Tooltip) return;
        if (!bootstrap.Tooltip.getInstance(el)) {
            const t = new bootstrap.Tooltip(el, { title: el.dataset.tt, placement: 'top', container: 'body', trigger: 'hover focus' });
            t.show();
        }
    });
})();
</script>
@endsection
