{{--
    Header institucional do Engaja (layout do Figma), usado na landing page e nas
    páginas públicas (login, cadastro, recuperação de senha etc. — layout de visitantes).
    Estilos em resources/sass/ui/_site-header.scss.
--}}
<header {{ $attributes->class(['site-header']) }}>
    <svg class="site-header__bg" viewBox="0 0 1600 69" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="site-header-fundo" x1="0" y1="0" x2="1600" y2="0" gradientUnits="userSpaceOnUse">
                <stop stop-color="#5E2068" />
                <stop offset="0.1" stop-color="#4E1A5B" />
                <stop offset="0.25" stop-color="#3F1349" />
                <stop offset="1" stop-color="#3E1448" />
            </linearGradient>
            <linearGradient id="site-header-onda-direita" x1="1250" y1="0" x2="1500" y2="0" gradientUnits="userSpaceOnUse">
                <stop stop-color="#BE6ED2" stop-opacity="0" />
                <stop offset="1" stop-color="#BE6ED2" stop-opacity="0.12" />
            </linearGradient>
        </defs>
        <path d="M1600 0H0V69H1600V0Z" fill="url(#site-header-fundo)" />
        <path d="M0 0H60C80 25 100 50 128 69H0V0Z" fill="#BE6ED2" fill-opacity="0.13" />
        <path d="M1250 0H1512C1470 25 1430 50 1388 69H1250V0Z" fill="url(#site-header-onda-direita)" />
    </svg>

    <div class="site-header__container">
        <a href="{{ url('/') }}" class="site-header__logo" aria-label="Engaja — página inicial">
            <img src="{{ asset('images/engaja-favicon.png') }}" alt="" width="40" height="46">
            <span>Engaja</span>
        </a>

        @guest
            <nav class="site-header__actions" aria-label="Acesso">
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="site-header__btn site-header__btn--claro"
                       @if (request()->routeIs('login')) aria-current="page" @endif>
                        Entrar
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false">
                            <path d="M3.75 9H14.25M9.75 13.5L14.25 9L9.75 4.5" stroke="#401648" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </a>
                @endif
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="site-header__btn site-header__btn--contorno"
                       @if (request()->routeIs('register')) aria-current="page" @endif>Cadastrar</a>
                @endif
            </nav>
        @endguest
    </div>
</header>
