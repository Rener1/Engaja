{{--
    Barra roxa com gradiente no topo das páginas internas.

    Props:
      - eyebrow:     texto pequeno em caixa alta acima do título (ex.: "Relatórios")
      - eyebrowIcon: ícone Bootstrap Icons do eyebrow, sem o prefixo "bi-"
      - title:       título da página (h1); use o slot "heading" quando precisar de HTML
      - subtitle:    linha de apoio abaixo do título
      - contained:   envolve o conteúdo em .container (páginas cujo corpo também usa .container)

    Slots:
      - actions: botões à direita (use .eg-hero-btn / .eg-hero-btn--solid)
      - heading: substitui o título simples
      - default: linhas extras de metadados abaixo do subtítulo
--}}
@props([
    'eyebrow' => null,
    'eyebrowIcon' => null,
    'title' => null,
    'subtitle' => null,
    'contained' => false,
])

<div {{ $attributes->class(['eg-hero', 'eg-hero--plain' => ! $contained]) }}>
    <div @class(['container' => $contained])>
        <div class="eg-hero__inner">
            <div class="eg-hero__text">
                @if ($eyebrow)
                    <span class="eg-hero__eyebrow">
                        @if ($eyebrowIcon)<i class="bi bi-{{ $eyebrowIcon }} me-1"></i>@endif{{ $eyebrow }}
                    </span>
                @endif

                @isset($heading)
                    <h1 {{ $heading->attributes->class(['eg-hero__title']) }}>{{ $heading }}</h1>
                @else
                    <h1 class="eg-hero__title">{{ $title }}</h1>
                @endisset

                @if ($subtitle)
                    <p class="eg-hero__subtitle">{{ $subtitle }}</p>
                @endif

                @if ($slot->hasActualContent())
                    <div class="eg-hero__meta">{{ $slot }}</div>
                @endif
            </div>

            @isset($actions)
                @if ($actions->hasActualContent())
                    <div {{ $actions->attributes->class(['eg-hero__actions']) }}>
                        {{ $actions }}
                    </div>
                @endif
            @endisset
        </div>
    </div>
</div>
