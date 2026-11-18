{{--
    Campo de filtro: label em caixa alta (com ícone opcional) + controle no slot.
    Props: label, icon (Bootstrap Icons sem "bi-"), for (id do controle).
--}}
@props([
    'label' => null,
    'icon' => null,
    'for' => null,
])

<div {{ $attributes }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="eg-filter-label">
            @if ($icon)<i class="bi bi-{{ $icon }} me-1"></i> @endif{{ $label }}
        </label>
    @endif
    {{ $slot }}
</div>
