@extends('layouts.app')

@section('content')

{{-- ══════════════════════════════════════════════════
     HERO HEADER
══════════════════════════════════════════════════ --}}
<x-ui.page-hero
    eyebrow="Relatórios" eyebrow-icon="bar-chart-line-fill"
    title="Consolidação de Avaliações"
    subtitle="Médias, distribuições e respostas consolidadas por modelo de avaliação."
    class="mb-0">
    @if ($evento)
        <x-slot:actions>
            <a id="btn-download-pdf"
               href="{{ route('avaliacoes-consolidadas.pdf', ['evento_id' => $evento->id, 'agrupamento' => $agrupamento]) }}"
               class="eg-hero-btn"
               target="_blank">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i>
                Baixar PDF
            </a>
            <a id="btn-download-docx"
               href="{{ route('avaliacoes-consolidadas.pdf', ['evento_id' => $evento->id, 'agrupamento' => $agrupamento, 'formato' => 'docx']) }}"
               class="eg-hero-btn">
                <i class="bi bi-file-earmark-word me-1"></i>
                Baixar Word
            </a>
        </x-slot:actions>
    @endif
</x-ui.page-hero>

<div class="py-4">

    {{-- ══════════════════════════════════════════════════
         FILTROS
    ══════════════════════════════════════════════════ --}}
    <x-ui.filter-card class="mb-5">
        <form id="filtros-form" method="GET" action="{{ route('avaliacoes-consolidadas.index') }}">
            <div class="row g-3 align-items-center">
                <x-ui.filter-field class="col-lg-7" label="Ação Pedagógica" icon="calendar-event" for="evento_id">
                    <x-ui.select name="evento_id" id="evento_id" class="js-autosubmit">
                        @foreach ($eventos as $eventoOpcao)
                            <option value="{{ $eventoOpcao->id }}" @selected($evento && $evento->id === $eventoOpcao->id)>
                                {{ $eventoOpcao->nome }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </x-ui.filter-field>
                <x-ui.filter-field class="col-lg-5" label="Agrupar por" icon="collection" for="agrupamento">
                    <x-ui.segmented id="agrupamento-group" name="agrupamento" autosubmit
                        :options="['geral' => 'Todos', 'regiao' => 'Região', 'municipio' => 'Município']"
                        :value="$agrupamento" />
                </x-ui.filter-field>
            </div>
        </form>
    </x-ui.filter-card>

    @if (! $evento)
        {{-- ESTADO VAZIO: sem seleção --}}
        <x-ui.empty-state icon="bar-chart-steps" title="Selecione uma ação pedagógica">
            Os dados de avaliação consolidados aparecerão aqui assim que você escolher uma ação acima.
        </x-ui.empty-state>

    @elseif (empty($grupos))
        <x-ui.empty-state icon="inbox" muted title="Sem respostas registradas">
            Nenhuma avaliação respondida foi encontrada para esta ação pedagógica.
        </x-ui.empty-state>

    @else
        @php
            $totalSubmissoes = collect($grupos)->sum(fn($g) => collect($g['templates'])->sum('submissoes'));
            $totalRespostas  = collect($grupos)->sum(fn($g) => collect($g['templates'])->sum('respostas'));
            $totalModelos    = collect($grupos)->sum(fn($g) => count($g['templates']));
        @endphp

        @php
            $agrupamentoLabel = ['regiao' => 'Por Região', 'municipio' => 'Por Município'][$agrupamento] ?? 'Todos os municípios';
            $agrupamentoIcon  = ['regiao' => 'map-fill', 'municipio' => 'geo-alt-fill'][$agrupamento] ?? 'globe2';
        @endphp

        <div class="eg-stats-row mb-5">
            <x-ui.stat-card color="purple" icon="people-fill" label="Submissões"
                :value="number_format($totalSubmissoes, 0, ',', '.')" />
            <x-ui.stat-card color="blue" icon="chat-dots-fill" label="Respostas coletadas"
                :value="number_format($totalRespostas, 0, ',', '.')" />
            <x-ui.stat-card color="green" icon="clipboard2-check-fill" label="Modelo(s) de avaliação"
                :value="$totalModelos" />
            <x-ui.stat-card color="orange" :icon="$agrupamentoIcon" label="Agrupamento ativo"
                :value="$agrupamentoLabel" small />
        </div>

        {{-- ══════════════════════════════════════════════════
             GRUPOS
        ══════════════════════════════════════════════════ --}}
        @foreach ($grupos as $grupoIndex => $grupo)
            <div class="csl-group mb-5">
                {{-- Cabeçalho do grupo --}}
                <div class="csl-group__header mb-4">
                    <div>
                        <h2 class="csl-group__name">{{ $grupo['nome'] }}</h2>
                        <span class="csl-group__meta">{{ count($grupo['templates']) }} modelo(s) de avaliação</span>
                    </div>
                    <div class="ms-auto">
                        <span class="csl-group__badge">
                            {{ collect($grupo['templates'])->sum('submissoes') }} submissões
                        </span>
                    </div>
                </div>

                @foreach ($grupo['templates'] as $templateIndex => $template)
                    @php
                        $rootId   = 'avaliacoes-consolidado-'.$grupoIndex.'-'.$templateIndex;
                        $mediaGeral = $template['media_geral'];
                        $mediaClass = $mediaGeral === null ? '' : ($mediaGeral >= 4 ? 'csl-media--green' : ($mediaGeral >= 3 ? 'csl-media--yellow' : 'csl-media--red'));
                    @endphp

                    <div class="csl-template-card mb-4">
                        {{-- Cabeçalho do template --}}
                        <div class="csl-template-card__header">
                            <div class="csl-template-card__info">
                                <div class="csl-model-badge">
                                    <i class="bi bi-clipboard2-check-fill me-1"></i>
                                    Modelo de Avaliação
                                </div>
                                <h3 class="csl-template-card__name">{{ $template['template_nome'] }}</h3>
                                <div class="csl-template-card__stats">
                                    <span>
                                        <i class="bi bi-person-check me-1"></i>
                                        <strong>{{ number_format($template['submissoes'] ?? 0, 0, ',', '.') }}</strong> submissões
                                    </span>
                                    <span class="csl-dot"></span>
                                    <span>
                                        <i class="bi bi-chat-dots me-1"></i>
                                        <strong>{{ number_format($template['respostas'] ?? 0, 0, ',', '.') }}</strong> respostas
                                    </span>
                                    <span class="csl-dot"></span>
                                    <span>
                                        <i class="bi bi-list-ol me-1"></i>
                                        <strong>{{ count($template['perguntas'] ?? []) }}</strong> perguntas
                                    </span>
                                </div>
                            </div>

                            {{-- Média geral --}}
                            @if ($mediaGeral !== null)
                            <div class="csl-media-block {{ $mediaClass }}">
                                <div class="csl-media-block__value">
                                    {{ number_format($mediaGeral, 1, ',', '.') }}
                                </div>
                                <div class="csl-media-block__label">Média geral</div>
                                <div class="csl-media-block__sub">
                                    {{ number_format($template['respostas_com_media'] ?? 0, 0, ',', '.') }} resp. em escala
                                </div>
                            </div>
                            @else
                            <div class="csl-media-block csl-media--muted">
                                <div class="csl-media-block__value">—</div>
                                <div class="csl-media-block__label">Sem escala</div>
                            </div>
                            @endif
                        </div>

                        {{-- Corpo: perguntas renderizadas via JS --}}
                        <div class="csl-template-card__body">
                            <div id="{{ $rootId }}" data-avaliacoes-inline-root data-text-modal-id="textAnswersModalConsolidado">
                                <script type="application/json" data-avaliacoes-perguntas>@json($template['perguntas'] ?? [])</script>
                                <div class="vstack gap-4" data-cards-questoes></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
</div>

{{-- Modal de respostas de texto --}}
<div class="modal fade" id="textAnswersModalConsolidado" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <x-ui.modal-header>
                <span class="js-text-modal-title"><i class="bi bi-chat-square-quote me-2"></i>Respostas abertas</span>
            </x-ui.modal-header>
            <div class="modal-body p-4">
                <div class="text-muted small mb-3 js-text-modal-count"></div>
                <div class="vstack gap-2 js-text-modal-list csl-modal-list"></div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
/* ──────────────────────────────────────────────────────
   GRUPO
────────────────────────────────────────────────────── */
.csl-group__header {
    display: flex;
    align-items: center;
    gap: .9rem;
}
.csl-group__name {
    font-size: 1.15rem;
    font-weight: 800;
    color: #1f2937;
    margin: 0;
    line-height: 1.2;
}
.csl-group__meta {
    font-size: .78rem;
    color: #9ca3af;
    font-weight: 600;
}
.csl-group__badge {
    background: #f3eaf4;
    color: #421944;
    font-size: .78rem;
    font-weight: 700;
    border-radius: 2rem;
    padding: .3rem .9rem;
    white-space: nowrap;
}

/* ──────────────────────────────────────────────────────
   TEMPLATE CARD
────────────────────────────────────────────────────── */
.csl-template-card {
    background: #fff;
    border-radius: 1.1rem;
    box-shadow: 0 4px 24px rgba(66,25,68,.07), 0 1px 4px rgba(0,0,0,.04);
    overflow: hidden;
    border: 1px solid #f0eaf4;
    transition: box-shadow .2s;
}
.csl-template-card:hover {
    box-shadow: 0 8px 32px rgba(66,25,68,.12), 0 2px 8px rgba(0,0,0,.06);
}
.csl-template-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.5rem;
    background: linear-gradient(to bottom, #faf7fb, #fff);
    border-bottom: 1px solid #f0eaf4;
    flex-wrap: wrap;
}
.csl-template-card__info {
    flex: 1;
    min-width: 0;
}
.csl-model-badge {
    display: inline-flex;
    align-items: center;
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #421944;
    background: #f3eaf4;
    border: 1px solid #d9bfdc;
    border-radius: 2rem;
    padding: .2rem .65rem;
    margin-bottom: .5rem;
}
.csl-template-card__name {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 .5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 580px;
}
.csl-template-card__stats {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .4rem;
    font-size: .8rem;
    color: #6b7280;
}
.csl-dot {
    display: inline-block;
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: #d1d5db;
}
.csl-template-card__body {
    padding: 1.5rem;
}

/* Média geral block */
.csl-media-block {
    text-align: center;
    padding: .85rem 1.25rem;
    border-radius: .85rem;
    min-width: 120px;
    flex-shrink: 0;
}
.csl-media-block__value {
    font-size: 2rem;
    font-weight: 900;
    line-height: 1;
}
.csl-media-block__label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    opacity: .7;
    margin-top: .2rem;
}
.csl-media-block__sub {
    font-size: .68rem;
    opacity: .55;
    margin-top: .15rem;
}
.csl-media--green  { background: #dcfce7; color: #16a34a; }
.csl-media--yellow { background: #fef9c3; color: #ca8a04; }
.csl-media--red    { background: #fee2e2; color: #dc2626; }
.csl-media--muted  { background: #f3f4f6; color: #9ca3af; }

/* ──────────────────────────────────────────────────────
   CARDS DE QUESTÕES (via JS)
────────────────────────────────────────────────────── */
[data-cards-questoes] .row.g-3 > .col-md-6:only-child,
[data-cards-questoes] .row.g-3 > .col-md-6:nth-child(odd):nth-last-child(1) {
    flex: 0 0 100%;
    max-width: 100%;
}
[data-cards-questoes] .row > [class*="col-"],
[data-cards-questoes] .question-body {
    min-width: 0;
}
.csl-modal-list {
    max-height: 60vh;
    overflow: auto;
}

@media (max-width: 576px) {
    .csl-template-card__header { flex-direction: column; }
    [data-cards-questoes] .question-header {
        flex-direction: column;
        align-items: flex-start;
        gap: .5rem;
    }
    [data-cards-questoes] .question-controls,
    [data-cards-questoes] .question-controls select {
        width: 100%;
        max-width: none;
    }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@vite(['resources/js/avaliacoes-distribuicao-charts.js'])
<script>
(function () {
    const form       = document.getElementById('filtros-form');
    const selEvento  = document.getElementById('evento_id');
    const selAgrup   = document.getElementById('agrupamento');
    const btnPdf     = document.getElementById('btn-download-pdf');
    const btnDocx    = document.getElementById('btn-download-docx');
    const baseUrlPdf = '{{ route('avaliacoes-consolidadas.pdf') }}';

    // ── Mantém os links de PDF/Word sincronizados com os filtros ──
    function atualizarPdf() {
        const query = '?evento_id=' + selEvento.value + '&agrupamento=' + selAgrup.value;
        if (btnPdf) btnPdf.href = baseUrlPdf + query;
        if (btnDocx) btnDocx.href = baseUrlPdf + query + '&formato=docx';
    }

    // O segmented control (componente ui.segmented com autosubmit) atualiza o hidden e submete sozinho.
    selAgrup.addEventListener('change', atualizarPdf);

    // ── Auto-submit no select de evento ───────────────────
    selEvento.addEventListener('change', function () {
        atualizarPdf();
        document.body.classList.add('eg-loading');
        form.submit();
    });

    atualizarPdf();
})();
</script>
@endpush
