{{-- Card branco dos filtros. Envolve o <form> da página (ou o próprio conteúdo). --}}
<div {{ $attributes->class(['eg-filter-card']) }}>
    {{ $slot }}
</div>
