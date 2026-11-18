{{--
    Paginação padrão do Engaja (registrada em AppServiceProvider via Paginator::defaultView),
    usada por todo {{ $paginator->links() }}. Para o bloco completo com espaçamento,
    prefira o componente <x-ui.pagination :paginator="$x" />.

    Mesma anatomia da paginação do AG Grid (<x-data-table>): contador à esquerda e
    |‹ ‹ Página [n] de N › ›| à direita. O campo da página navega via
    resources/js/lib/pagination.js (Enter ou ao sair do campo).
--}}
@if ($paginator->hasPages())
    @php
        $formatarNumero = fn ($n) => number_format((int) $n, 0, ',', '.');
    @endphp
    <nav class="eg-pagination" role="navigation" aria-label="Paginação">
        <p class="eg-pagination__info">
            {{ __('Showing') }}
            <strong>{{ $formatarNumero($paginator->firstItem()) }}</strong> – <strong>{{ $formatarNumero($paginator->lastItem()) }}</strong>
            {{ __('of') }}
            <strong>{{ $formatarNumero($paginator->total()) }}</strong>
            {{ __('results') }}
        </p>

        <div class="eg-pagination__list">
            @if ($paginator->onFirstPage())
                <span class="eg-page eg-page--nav is-disabled" aria-disabled="true" aria-label="Primeira página">
                    <i class="bi bi-chevron-bar-left" aria-hidden="true"></i>
                </span>
                <span class="eg-page eg-page--nav is-disabled" aria-disabled="true" aria-label="Página anterior">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </span>
            @else
                <a class="eg-page eg-page--nav" href="{{ $paginator->url(1) }}" aria-label="Primeira página">
                    <i class="bi bi-chevron-bar-left" aria-hidden="true"></i>
                </a>
                <a class="eg-page eg-page--nav" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </a>
            @endif

            <span class="eg-pagination__page">
                <span>Página</span>
                <input type="number" class="eg-pagination__input" data-eg-page-input
                       value="{{ $paginator->currentPage() }}" min="1" max="{{ $paginator->lastPage() }}"
                       data-base-url="{{ $paginator->url(1) }}" data-page-name="{{ $paginator->getPageName() }}"
                       aria-label="Página atual, {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}">
                <span>de</span>
                <span class="eg-pagination__total">{{ $formatarNumero($paginator->lastPage()) }}</span>
            </span>

            @if ($paginator->hasMorePages())
                <a class="eg-page eg-page--nav" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Próxima página">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
                <a class="eg-page eg-page--nav" href="{{ $paginator->url($paginator->lastPage()) }}" aria-label="Última página">
                    <i class="bi bi-chevron-bar-right" aria-hidden="true"></i>
                </a>
            @else
                <span class="eg-page eg-page--nav is-disabled" aria-disabled="true" aria-label="Próxima página">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </span>
                <span class="eg-page eg-page--nav is-disabled" aria-disabled="true" aria-label="Última página">
                    <i class="bi bi-chevron-bar-right" aria-hidden="true"></i>
                </span>
            @endif
        </div>
    </nav>
@endif
