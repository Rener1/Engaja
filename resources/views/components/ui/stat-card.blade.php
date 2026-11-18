{{--
    Card de indicador (KPI). Usar dentro de <div class="eg-stats-row">.
    Props: value, label, icon (Bootstrap Icons sem "bi-"), color (purple|blue|green|orange|pink|gray),
           small (valor textual menor), hint (linha extra abaixo do label),
           valueId (id no elemento do valor, p/ scripts que o atualizam).

    value/label/hint também podem ser slots nomeados com atributos — útil quando
    um script atualiza o elemento (ex.: <x-slot:value data-total="eventos">-</x-slot>).
--}}
@props([
    'value' => null,
    'label' => null,
    'icon' => null,
    'color' => 'purple',
    'small' => false,
    'hint' => null,
    'valueId' => null,
])

@php
    $slotAttributes = fn ($part) => $part instanceof \Illuminate\View\ComponentSlot
        ? $part->attributes
        : new \Illuminate\View\ComponentAttributeBag();
@endphp

<div {{ $attributes->class(['eg-stat-card', 'eg-stat-card--'.$color]) }}>
    <div class="eg-stat-body">
        <div {{ $slotAttributes($value)->class(['eg-stat-value', 'eg-stat-value--sm' => $small]) }} @if ($valueId) id="{{ $valueId }}" @endif>{{ $value ?? $slot }}</div>
        <div {{ $slotAttributes($label)->class(['eg-stat-label']) }}>{{ $label }}</div>
        @if ($hint !== null)
            <div {{ $slotAttributes($hint)->class(['eg-stat-hint']) }}>{{ $hint }}</div>
        @endif
    </div>
    @if ($icon)
        <div class="eg-stat-icon">
            <i class="bi bi-{{ $icon }}"></i>
        </div>
    @endif
</div>
