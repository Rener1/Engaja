{{--
    Cabeçalho de modal roxo com botão fechar branco.
    Props: title, icon (Bootstrap Icons sem "bi-"), titleId (id do h5, p/ aria-labelledby),
           danger (variante rosa p/ confirmações destrutivas), dismissible.
    Slot padrão substitui o título simples quando precisar de HTML.
--}}
@props([
    'title' => null,
    'icon' => null,
    'titleId' => null,
    'danger' => false,
    'dismissible' => true,
])

<div {{ $attributes->class(['modal-header', 'eg-modal-header', 'eg-modal-header--danger' => $danger]) }}>
    <h5 class="modal-title" @if ($titleId) id="{{ $titleId }}" @endif>
        @if ($icon)<i class="bi bi-{{ $icon }} me-2"></i>@endif{{ $title ?? $slot }}
    </h5>
    @if ($dismissible)
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
    @endif
</div>
