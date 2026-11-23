<section class="cpe-report-card cpe-report-volunteers" id="{{ $id }}" aria-labelledby="{{ $id }}-titulo">
    <header class="cpe-report-volunteers-header">
        <h2 id="{{ $id }}-titulo">{{ $titulo }}</h2>
        <p class="cpe-report-description">{{ $descricao }}</p>
        <span class="cpe-report-total">{{ number_format($voluntarios->total(), 0, ',', '.') }} {{ $voluntarios->total() === 1 ? 'voluntário' : 'voluntários' }}</span>
    </header>

    <div class="cpe-report-volunteers-scroll" tabindex="0" role="region" aria-labelledby="{{ $id }}-titulo">
        <ul class="cpe-report-volunteers-list">
            @forelse ($voluntarios as $voluntario)
                <li class="cpe-report-volunteer">
                    <div class="cpe-report-volunteer-identity">
                        <strong>{{ $voluntario->name }}</strong>
                        <span>{{ $voluntario->email }}</span>
                    </div>
                    @if ($mostrarPendencias)
                        <span class="cpe-report-pending-count">
                            <strong>{{ number_format($voluntario->cartas_pendentes_count, 0, ',', '.') }}</strong>
                            {{ $voluntario->cartas_pendentes_count === 1 ? 'carta sem resposta' : 'cartas sem resposta' }}
                        </span>
                    @endif
                </li>
            @empty
                <li class="cpe-report-empty">{{ $mensagemVazia }}</li>
            @endforelse
        </ul>
    </div>

    @if ($voluntarios->hasPages())
        <nav class="cpe-report-pagination" aria-label="Paginação: {{ $titulo }}">
            @if ($voluntarios->onFirstPage())
                <span aria-disabled="true">Anterior</span>
            @else
                <a href="{{ $voluntarios->previousPageUrl() }}" rel="prev">Anterior</a>
            @endif
            <span>Página {{ $voluntarios->currentPage() }} de {{ $voluntarios->lastPage() }}</span>
            @if ($voluntarios->hasMorePages())
                <a href="{{ $voluntarios->nextPageUrl() }}" rel="next">Próxima</a>
            @else
                <span aria-disabled="true">Próxima</span>
            @endif
        </nav>
    @endif
</section>
