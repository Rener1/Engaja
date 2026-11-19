{{--
    Rodapé institucional do Engaja (layout do Figma), usado na landing page e
    no layout da aplicação (layouts/footer). Estilos em resources/sass/ui/_site-footer.scss.
    Atributos extras (ex.: class="mt-5") são repassados ao <footer>.
--}}
<footer {{ $attributes->class(['site-footer']) }}>
    <svg class="site-footer__bg" viewBox="0 0 1600 306" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="site-footer-fundo" x1="800" y1="0" x2="800" y2="306" gradientUnits="userSpaceOnUse">
                <stop stop-color="#621F6E" />
                <stop offset="1" stop-color="#320F3C" />
            </linearGradient>
            <linearGradient id="site-footer-clareamento" x1="0" y1="153" x2="1600" y2="153" gradientUnits="userSpaceOnUse">
                <stop stop-color="#BE6ED2" stop-opacity="0.087" />
                <stop offset="0.15" stop-color="#BE6ED2" stop-opacity="0.044" />
                <stop offset="0.3" stop-color="#BE6ED2" stop-opacity="0.016" />
                <stop offset="0.5" stop-color="#BE6ED2" stop-opacity="0" />
                <stop offset="0.7" stop-color="#BE6ED2" stop-opacity="0.016" />
                <stop offset="0.85" stop-color="#BE6ED2" stop-opacity="0.044" />
                <stop offset="1" stop-color="#BE6ED2" stop-opacity="0.087" />
            </linearGradient>
        </defs>
        <rect width="1600" height="306" fill="url(#site-footer-fundo)" />
        <rect width="1600" height="306" fill="url(#site-footer-clareamento)" />
        <path d="M0 40C41.2987 45.3868 79.3735 54.9644 116.019 67.4951C78.3347 69.9686 39.715 74.0427 0 80V40Z" fill="#BE6ED2" fill-opacity="0.117647" />
        <path d="M1180 0C1351.28 38.0618 1441.07 103.285 1578.6 126.748C1502.13 150.278 1429.81 166.191 1360.72 176.135C1209.18 119.76 1092.34 48.0842 900 0H1180Z" fill="#BE6ED2" fill-opacity="0.117647" />
        <path d="M1600 120C1592.83 122.316 1585.7 124.564 1578.6 126.748C1441.07 103.285 1351.28 38.0618 1180 0H1600V120Z" fill="#BE6ED2" fill-opacity="0.109804" />
        <path d="M0 80C600 -9.99997 950 330 1600 120V306H0V80Z" fill="#180622" fill-opacity="0.235294" />
    </svg>

    <div class="site-footer__container">
        <a href="{{ url('/') }}" class="site-footer__logo" aria-label="Engaja — página inicial">
            <img src="{{ asset('images/engaja-favicon.png') }}" alt="" width="48" height="56">
            <span>Engaja</span>
        </a>

        <p class="site-footer__slogan">Sistema de Gestão de Participação e Engajamento.</p>

        <div class="site-footer__partners">
            <div class="site-footer__partner">
                <span class="site-footer__partner-label">Realização</span>
                <div class="site-footer__partner-logo">
                    <img src="{{ asset('images/ipf-white.png') }}" alt="Instituto Paulo Freire" width="206" height="45">
                </div>
            </div>
            <div class="site-footer__partner">
                <span class="site-footer__partner-label">Parceria</span>
                <div class="site-footer__partner-logo">
                    <img src="{{ asset('images/petrobras-white.png') }}" alt="Petrobras" width="211" height="42">
                </div>
            </div>
        </div>

        <p class="site-footer__legal">INSTITUTO DE EDUCAÇÃO E DIREITOS HUMANOS PAULO FREIRE   |   CNPJ 04.950.603/0001-05</p>
    </div>
</footer>
