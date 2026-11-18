{{-- Paginação simples (simplePaginate) no padrão visual do Engaja. --}}
@if ($paginator->hasPages())
    <nav class="eg-pagination eg-pagination--simple" role="navigation" aria-label="Paginação">
        <p class="eg-pagination__info">Página <strong>{{ $paginator->currentPage() }}</strong></p>

        <ul class="eg-pagination__list">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="eg-page eg-page--nav eg-page--text is-disabled" aria-disabled="true">
                        <i class="bi bi-chevron-left me-1" aria-hidden="true"></i>Anterior
                    </span>
                @else
                    <a class="eg-page eg-page--nav eg-page--text" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                        <i class="bi bi-chevron-left me-1" aria-hidden="true"></i>Anterior
                    </a>
                @endif
            </li>
            <li>
                @if ($paginator->hasMorePages())
                    <a class="eg-page eg-page--nav eg-page--text" href="{{ $paginator->nextPageUrl() }}" rel="next">
                        Próxima<i class="bi bi-chevron-right ms-1" aria-hidden="true"></i>
                    </a>
                @else
                    <span class="eg-page eg-page--nav eg-page--text is-disabled" aria-disabled="true">
                        Próxima<i class="bi bi-chevron-right ms-1" aria-hidden="true"></i>
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
