{{--
    Select no padrão visual dos filtros (fundo cinza, borda arredondada, chevron).
    Todos os atributos (id, name, data-*, wire:model, onchange, multiple) são repassados ao <select>.
    O chevron é omitido em selects múltiplos/TomSelect.
--}}
@php
    $isMultiple = $attributes->has('multiple') || $attributes->has('data-multiselect');
@endphp

<div class="eg-select-wrapper">
    <select {{ $attributes->class(['eg-select']) }}>
        {{ $slot }}
    </select>
    @unless ($isMultiple)
        <i class="bi bi-chevron-down eg-select-chevron" aria-hidden="true"></i>
    @endunless
</div>
