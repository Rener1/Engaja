<article class="cpe-report-card question-card" data-cartas-chart="{{ $id }}" aria-labelledby="grafico-{{ $id }}-titulo">
    <div class="question-head">
        <h2 class="question-title" id="grafico-{{ $id }}-titulo">{{ $titulo }}</h2>
        <p class="cpe-report-description">{{ $descricao }}</p>
    </div>
    <div class="question-meta">
        <span class="question-count">{{ number_format($grafico['total'], 0, ',', '.') }} {{ $unidade }}</span>
        <div class="question-controls">
            <label for="grafico-{{ $id }}-tipo" class="visually-hidden">Tipo de exibição: {{ $titulo }}</label>
            <select id="grafico-{{ $id }}-tipo" class="form-select form-select-sm" data-chart-type @disabled($grafico['total'] === 0)>
                <option value="auto">Auto</option>
                <option value="bar">Barras (vertical)</option>
                <option value="bar-horizontal">Barras (horizontal)</option>
                <option value="doughnut">Pizza</option>
                <option value="polarArea">Polar</option>
                <option value="line">Linha</option>
            </select>
        </div>
    </div>
    @if ($grafico['total'] > 0)
        <div class="question-body">
            <div class="cpe-report-chart-layout" data-chart-layout>
                <div class="cpe-report-chart-canvas">
                    <canvas data-chart-canvas role="img" aria-label="{{ $titulo }}"></canvas>
                </div>
                <ul class="cpe-report-chart-legend" data-chart-legend aria-label="Valores do gráfico">
                    @foreach ($grafico['labels'] as $indice => $rotulo)
                        <li>
                            <span>{{ $rotulo }}</span>
                            <strong>{{ number_format($grafico['values'][$indice], 0, ',', '.') }}</strong>
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="cpe-report-chart-error" data-chart-error role="alert" hidden>Não foi possível exibir o gráfico. Recarregue a página para tentar novamente.</p>
        </div>
    @else
        <p class="cpe-report-empty">Ainda não há dados para este gráfico.</p>
    @endif
</article>
