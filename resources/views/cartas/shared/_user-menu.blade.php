<div class="cpe-floating-user">
    <button class="cpe-user-trigger" type="button" aria-label="Menu do usuário" aria-expanded="false">
        <span>{{ Auth::user()->name }}</span>
        <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <div class="cpe-user-dropdown">
        @if(Auth::user()->hasRole('cartas_admin'))
            <a href="{{ route('cartas.usuarios.index') }}">Gerenciar usuários</a>
        @endif
        @can('cartas.ver-relatorios')
            <a href="{{ route('cartas.relatorios.index') }}">Relatórios</a>
        @endcan
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Sair</button>
        </form>
    </div>
</div>
