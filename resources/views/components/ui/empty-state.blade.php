{{--
    Estado vazio. Props: icon (Bootstrap Icons sem "bi-"), title, muted (ícone cinza),
    compact (sem sombra/padding grande — para dentro de cards e tabelas).
    Slot: texto de apoio.
--}}
@props([
    'icon' => 'inbox',
    'title' => null,
    'muted' => false,
    'compact' => false,
])

<div {{ $attributes->class(['eg-empty-state', 'eg-empty-state--compact' => $compact]) }}>
    <div @class(['eg-empty-icon', 'eg-empty-icon--muted' => $muted])>
        <i class="bi bi-{{ $icon }}"></i>
    </div>
    @if ($title)
        <h5>{{ $title }}</h5>
    @endif
    @if ($slot->hasActualContent())
        <p>{{ $slot }}</p>
    @endif
</div>
