{{--
    Cabeçalho de modal roxo com botão fechar branco.
    Props: title, icon (Bootstrap Icons sem "bi-"), titleId (id do h5, p/ aria-labelledby),
           danger (variante rosa p/ confirmações destrutivas), warning (âmbar p/ avisos), dismissible.
    Slots: padrão substitui o título simples quando precisar de HTML;
           "subtitle" (opcional) é uma linha menor abaixo do título (aceita atributos, ex.: id).
--}}
@props([
    'title' => null,
    'icon' => null,
    'titleId' => null,
    'danger' => false,
    'warning' => false,
    'dismissible' => true,
])

<div {{ $attributes->class(['modal-header', 'eg-modal-header', 'eg-modal-header--danger' => $danger, 'eg-modal-header--warning' => $warning]) }}>
    <div class="min-w-0">
        <h5 class="modal-title" @if ($titleId) id="{{ $titleId }}" @endif>
            @if ($icon)<i class="bi bi-{{ $icon }} me-2"></i>@endif{{ $title ?? $slot }}
        </h5>
        @isset($subtitle)
            <div {{ $subtitle->attributes->class(['eg-modal-header__subtitle']) }}>{{ $subtitle }}</div>
        @endisset
    </div>
    @if ($dismissible)
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
    @endif
</div>
