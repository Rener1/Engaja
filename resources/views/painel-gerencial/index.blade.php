@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<x-ui.page-hero contained
    eyebrow="Relatórios" eyebrow-icon="speedometer2"
    title="Painel Gerencial de Quantitativos"
    subtitle="Quadro de referência: público esperado × inscritos × presentes × avaliações, por Ação Pedagógica."
    class="mb-0">
    <x-slot:actions>
        <a href="{{ route('painel-gerencial.exportar') }}?{{ http_build_query(array_merge(request()->query(), ['formato' => 'pdf'])) }}" class="eg-hero-btn" title="Exportar como PDF">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF
        </a>
        <a href="{{ route('painel-gerencial.exportar') }}?{{ http_build_query(array_merge(request()->query(), ['formato' => 'xlsx'])) }}" class="eg-hero-btn" title="Exportar como Excel">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel
        </a>
        <a href="{{ route('painel-gerencial.exportar') }}?{{ http_build_query(array_merge(request()->query(), ['formato' => 'docx'])) }}" class="eg-hero-btn" title="Exportar como Word">
            <i class="bi bi-file-earmark-word me-1"></i> Word
        </a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="container py-4">

    {{-- Filtros --}}
    <x-ui.filter-card>
        <form method="GET" action="{{ route('painel-gerencial.index') }}" class="row g-3 align-items-end">
            <x-ui.filter-field class="col-md-6 col-lg-3" label="Ação" icon="calendar-event" for="filter-evento">
                <x-ui.select name="evento_id" id="filter-evento">
                    <option value="">Todas</option>
                    @foreach($eventos as $id => $nome)
                        <option value="{{ $id }}" @selected(request('evento_id') == $id)>{{ $nome }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.filter-field>
            <x-ui.filter-field class="col-md-6 col-lg-2" label="Região" icon="map" for="filter-regiao">
                <x-ui.select name="regiao_id" id="filter-regiao">
                    <option value="">Todas</option>
                    @foreach($regioes as $regiao)
                        <option value="{{ $regiao->id }}" @selected(request('regiao_id') == $regiao->id)>{{ $regiao->nome }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.filter-field>
            <x-ui.filter-field class="col-md-6 col-lg-3" label="Município" icon="geo-alt" for="filter-municipio">
                <x-ui.select name="municipio_id" id="filter-municipio">
                    <option value="">Todos</option>
                    @foreach($municipios as $municipio)
                        <option value="{{ $municipio->id }}" @selected(request('municipio_id') == $municipio->id)>{{ $municipio->nome_com_estado }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.filter-field>
            <x-ui.filter-field class="col-md-6 col-lg-2" label="Intervalo" icon="calendar-range" for="filter-daterange">
                <x-ui.input id="filter-daterange" placeholder="De ... até" />
                <input type="hidden" name="de" id="filter-de" value="{{ request('de') }}">
                <input type="hidden" name="ate" id="filter-ate" value="{{ request('ate') }}">
            </x-ui.filter-field>
            <x-ui.filter-field class="col-md-6 col-lg-2" label="Período" icon="clock" for="filter-periodo">
                <x-ui.select name="periodo" id="filter-periodo">
                    <option value="">Todos</option>
                    <option value="manha" @selected(request('periodo') == 'manha')>Manhã</option>
                    <option value="tarde" @selected(request('periodo') == 'tarde')>Tarde</option>
                    <option value="noite" @selected(request('periodo') == 'noite')>Noite</option>
                </x-ui.select>
            </x-ui.filter-field>
            <div class="col-12 eg-filter-actions justify-content-end">
                <a href="{{ route('painel-gerencial.index') }}" class="btn btn-outline-secondary">Limpar</a>
                <button type="submit" class="btn btn-engaja"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            </div>
        </form>
    </x-ui.filter-card>

    {{-- KPIs --}}
    @php
        $cards = [
            ['Municípios ativos', $kpis['municipios_ativos'], 'geo-alt-fill', 'purple'],
            ['Participantes totais', $kpis['participantes_totais'], 'people-fill', 'blue'],
            ['Participantes únicos', $kpis['participantes_unicos'], 'person-check-fill', 'green'],
            ['Eventos realizados', $kpis['eventos_realizados'], 'calendar-event-fill', 'orange'],
            ['Horas presenciais', $kpis['horas_presenciais'], 'building-fill', 'purple'],
            ['Horas EaD', $kpis['horas_ead'], 'laptop-fill', 'blue'],
            ['Certificados emitidos', $kpis['certificados_emitidos'], 'award-fill', 'green'],
            ['Avaliações respondidas', $kpis['avaliacoes_respondidas'], 'clipboard2-check-fill', 'orange'],
            ['Pendências de documentação', $kpis['pendencias_documentacao'], 'exclamation-triangle-fill', 'pink'],
        ];
    @endphp
    <div class="eg-stats-row eg-stats-row--auto mb-4">
        @foreach($cards as [$label, $valor, $icon, $cor])
            <x-ui.stat-card :color="$cor" :icon="$icon" :label="$label"
                :value="is_float($valor) ? number_format($valor, 1, ',', '.') : number_format($valor, 0, ',', '.')" />
        @endforeach
    </div>

    {{-- Gráficos --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <h2 class="h6">Metas previstas × realizadas (por ação)</h2>
                <div style="position:relative;height:300px"><canvas id="chartMetas"></canvas></div>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <h2 class="h6">Participação por região</h2>
                <div style="position:relative;height:300px"><canvas id="chartRegiao"></canvas></div>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <h2 class="h6">Comparação entre segmentos</h2>
                <div style="position:relative;height:300px"><canvas id="chartSegmentos"></canvas></div>
            </div></div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <h2 class="h6">Evolução semestral</h2>
                <div style="position:relative;height:300px"><canvas id="chartEvolucao"></canvas></div>
            </div></div>
        </div>
    </div>

    {{-- Metas por ação (tabela) --}}
    @php
        $colunasMetas = [
            ['field' => 'acao', 'headerName' => 'Ação', 'flex' => 2],
            ['field' => 'previstas', 'headerName' => 'Previstas', 'flex' => 1, 'align' => 'end'],
            ['field' => 'inscritos', 'headerName' => 'Inscritos', 'flex' => 1, 'align' => 'end'],
            ['field' => 'presentes', 'headerName' => 'Presentes', 'flex' => 1, 'align' => 'end'],
            ['field' => 'avaliacoes', 'headerName' => 'Avaliações', 'flex' => 1, 'align' => 'end'],
            ['field' => 'pct_realizado', 'headerName' => '% Realizado', 'flex' => 1, 'align' => 'end'],
        ];
        $linhasMetas = collect($metas_por_acao)->map(fn ($r) => [
            'acao' => $r['acao'],
            'previstas' => $r['previstas'],
            'inscritos' => $r['inscritos'],
            'presentes' => $r['presentes'],
            'avaliacoes' => $r['avaliacoes'],
            'pct_realizado' => number_format($r['pct_realizado'], 1, ',', '.') . '%',
        ])->values();
    @endphp
    <div class="card shadow-sm mb-4"><div class="card-body">
        <h2 class="h6 mb-3">Metas por Ação Pedagógica</h2>
        <x-data-table class="dt-no-border" id="grid-painel-metas" :columns="$colunasMetas" :rows="$linhasMetas" :pagination="false" dom-layout="normal" style="height: 440px" />
    </div></div>

    {{-- Listas de acompanhamento --}}
    @php
        $colunasBaixoEngajamento = [
            ['field' => 'municipio', 'headerName' => 'Município', 'flex' => 2, 'html' => true],
            ['field' => 'pct_realizado', 'headerName' => '% Realizado', 'flex' => 1, 'align' => 'end'],
        ];
        $linhasBaixoEngajamento = collect($municipios_baixo_engajamento)->map(fn ($r) => [
            'municipio' => e($r['municipio']) . ' <span class="text-muted small">(' . e($r['regiao']) . ')</span>',
            'pct_realizado' => '<span class="text-danger">' . number_format($r['pct_realizado'], 1, ',', '.') . '%</span>',
        ])->values();

        $colunasSemAvaliacao = [
            ['field' => 'acao_momento', 'headerName' => 'Ação / Momento', 'flex' => 2, 'html' => true],
            ['field' => 'municipio', 'headerName' => 'Município', 'flex' => 1],
        ];
        $linhasSemAvaliacao = collect($eventos_sem_avaliacao)->map(fn ($r) => [
            'acao_momento' => e($r['acao']) . '<br><span class="text-muted small">' . e($r['momento']) . '</span>',
            'municipio' => $r['municipio'],
        ])->values();

        $colunasRecorrenciaAusencia = [
            ['field' => 'participante', 'headerName' => 'Participante', 'flex' => 2, 'html' => true],
            ['field' => 'ausencias', 'headerName' => 'Ausências', 'flex' => 1, 'align' => 'end'],
        ];
        $linhasRecorrenciaAusencia = collect($recorrencia_ausencia)->map(fn ($r) => [
            'participante' => e($r['participante']) . ' <span class="text-muted small">(' . e($r['municipio']) . ')</span>',
            'ausencias' => $r['ausencias'],
        ])->values();
    @endphp
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm h-100"><div class="card-body">
                <h2 class="h6 mb-3">Municípios com baixo engajamento</h2>
                <x-data-table class="dt-no-border" id="grid-painel-baixo-engajamento" :columns="$colunasBaixoEngajamento" :rows="$linhasBaixoEngajamento" :pagination="false" dom-layout="normal" style="height: 340px" />
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100"><div class="card-body">
                <h2 class="h6 mb-3">Eventos sem avaliação registrada</h2>
                <x-data-table class="dt-no-border" id="grid-painel-sem-avaliacao" :columns="$colunasSemAvaliacao" :rows="$linhasSemAvaliacao" :pagination="false" dom-layout="normal" style="height: 340px" />
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm h-100"><div class="card-body">
                <h2 class="h6 mb-3">Participantes com recorrência de ausência</h2>
                <x-data-table class="dt-no-border" id="grid-painel-recorrencia-ausencia" :columns="$colunasRecorrenciaAusencia" :rows="$linhasRecorrenciaAusencia" :pagination="false" dom-layout="normal" style="height: 340px" />
            </div></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/rangePlugin.js"></script>
<script>
(function () {
    const metas      = @json($metas_por_acao);
    const regioes    = @json($participacao_por_regiao);
    const segmentos  = @json($segmentos);
    const evolucao   = @json($evolucao_semestral);
    const cor = '#421944';
    const cor2 = '#b07cb3';

    new Chart(document.getElementById('chartMetas'), {
        type: 'bar',
        data: {
            labels: metas.map(m => m.acao),
            datasets: [
                { label: 'Previstas', data: metas.map(m => m.previstas), backgroundColor: cor2 },
                { label: 'Presentes', data: metas.map(m => m.presentes), backgroundColor: cor },
            ],
        },
        options: { maintainAspectRatio: false, responsive: true, plugins: { legend: { position: 'bottom' } } },
    });

    new Chart(document.getElementById('chartRegiao'), {
        type: 'bar',
        data: {
            labels: regioes.map(r => r.regiao),
            datasets: [
                { label: 'Previstas', data: regioes.map(r => r.previstas), backgroundColor: cor2 },
                { label: 'Presentes', data: regioes.map(r => r.presentes), backgroundColor: cor },
            ],
        },
        options: { maintainAspectRatio: false, responsive: true, plugins: { legend: { position: 'bottom' } } },
    });

    new Chart(document.getElementById('chartSegmentos'), {
        type: 'doughnut',
        data: {
            labels: segmentos.map(s => s.segmento),
            datasets: [{ data: segmentos.map(s => s.presentes), backgroundColor: ['#421944', '#b07cb3', '#e8daea', '#8a5a8c', '#cbb0cc'] }],
        },
        options: { maintainAspectRatio: false, responsive: true, plugins: { legend: { position: 'bottom' } } },
    });

    new Chart(document.getElementById('chartEvolucao'), {
        type: 'line',
        data: {
            labels: evolucao.map(e => e.semestre),
            datasets: [
                { label: 'Presentes', data: evolucao.map(e => e.presentes), borderColor: cor, backgroundColor: 'rgba(66,25,68,0.15)', fill: true },
                { label: 'Avaliações', data: evolucao.map(e => e.avaliacoes), borderColor: cor2, backgroundColor: 'rgba(176,124,179,0.15)', fill: true },
            ],
        },
        options: { maintainAspectRatio: false, responsive: true, plugins: { legend: { position: 'bottom' } } },
    });

    // Flatpickr range
    const deInput = document.getElementById('filter-de');
    const ateInput = document.getElementById('filter-ate');
    flatpickr(document.getElementById('filter-daterange'), {
        mode: 'range', dateFormat: 'Y-m-d', locale: { rangeSeparator: ' até ' },
        defaultDate: [deInput.value, ateInput.value].filter(Boolean),
        onChange: function (selectedDates) {
            deInput.value  = selectedDates[0] ? flatpickr.formatDate(selectedDates[0], 'Y-m-d') : '';
            ateInput.value = selectedDates[1] ? flatpickr.formatDate(selectedDates[1], 'Y-m-d') : '';
        },
    });

    // Cascata: ao trocar a ação, recarrega municípios
    const eventoSelect = document.getElementById('filter-evento');
    const municipioSelect = document.getElementById('filter-municipio');
    if (eventoSelect) {
        eventoSelect.addEventListener('change', function () {
            const url = '{{ route('painel-gerencial.momentos') }}?evento_id=' + (this.value || '');
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    const atual = municipioSelect.value;
                    municipioSelect.innerHTML = '<option value="">Todos</option>';
                    data.municipios.forEach(m => {
                        const opt = document.createElement('option');
                        opt.value = m.id; opt.textContent = m.nome;
                        if (String(m.id) === atual) opt.selected = true;
                        municipioSelect.appendChild(opt);
                    });
                });
        });
    }
})();
</script>
@endsection
