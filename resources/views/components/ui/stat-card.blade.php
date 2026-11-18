{{--
    Card de indicador (KPI). Usar dentro de <div class="eg-stats-row">.
    Props: value, label, icon (Bootstrap Icons sem "bi-"), color (purple|blue|green|orange|pink|gray),
           small (valor textual menor), hint (linha extra abaixo do label).
--}}
@props([
    'value' => null,
    'label' => null,
    'icon' => null,
    'color' => 'purple',
    'small' => false,
    'hint' => null,
])

<div {{ $attributes->class(['eg-stat-card', 'eg-stat-card--'.$color]) }}>
    <div class="eg-stat-body">
        <div @class(['eg-stat-value', 'eg-stat-value--sm' => $small])>{{ $value ?? $slot }}</div>
        <div class="eg-stat-label">{{ $label }}</div>
        @if ($hint)
            <div class="eg-stat-hint">{{ $hint }}</div>
        @endif
    </div>
    @if ($icon)
        <div class="eg-stat-icon">
            <i class="bi bi-{{ $icon }}"></i>
        </div>
    @endif
</div>
