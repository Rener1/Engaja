<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ config('app.name', 'Engaja') }}</title>
  <link rel="icon" type="image/png" href="{{ asset('images/engaja-favicon.png') }}">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@500;700&display=swap" rel="stylesheet">

  @vite(['resources/sass/app.scss'])

  <style>
    /* Landing page (Figma "Engaja — Landing Page", frame 1600×900). */
    .landing {
      --landing-container: 1064px;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: #fff;
      font-family: 'Montserrat', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
    }

    .landing-container {
      position: relative;
      z-index: 1;
      width: min(var(--landing-container), 100% - 32px);
      margin-inline: auto;
    }

    .landing-bg {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      display: block;
      pointer-events: none;
    }

    /* ===== Botões ===== */
    .landing-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      padding: 0 16px;
      font-family: 'Poppins', system-ui, sans-serif;
      font-weight: 700;
      line-height: normal;
      white-space: nowrap;
      text-decoration: none;
      transition: background-color .15s ease, border-color .15s ease, color .15s ease, filter .15s ease, transform .15s ease;
    }

    .landing-btn:hover {
      transform: translateY(-1px);
    }

    .landing-btn:focus-visible {
      outline: 3px solid #b9a1ef;
      outline-offset: 2px;
    }

    .landing-btn svg {
      display: block;
      flex-shrink: 0;
    }

    .landing-btn--claro {
      gap: 8px;
      width: 120px;
      height: 44px;
      border-radius: 22px;
      background: #f6eef9;
      color: #401648;
      font-size: 15px;
      filter: drop-shadow(0 3px 4px rgba(15, 3, 22, .24));
    }

    .landing-btn--claro:hover {
      background: #fff;
      color: #401648;
    }

    .landing-btn--contorno-claro {
      width: 132px;
      height: 44px;
      border: 2px solid #e2c4e8;
      border-radius: 22px;
      color: #fff;
      font-size: 15px;
    }

    .landing-btn--contorno-claro:hover {
      background: rgba(226, 196, 232, .14);
      border-color: #fff;
      color: #fff;
    }

    .landing-btn--escuro {
      gap: 10px;
      width: 164px;
      height: 53px;
      border-radius: 26.5px;
      background: #401644;
      color: #fff;
      font-size: 18px;
    }

    .landing-btn--escuro:hover {
      background: #5a1f60;
      color: #fff;
    }

    .landing-btn--contorno {
      width: 153px;
      height: 54px;
      border: 1.5px solid #8e2493;
      border-radius: 27px;
      color: #7a168c;
      font-size: 18px;
    }

    .landing-btn--contorno:hover {
      background: #f6eef9;
      color: #7a168c;
    }

    /* ===== Marca ===== */
    .landing-logo {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      color: #fff;
      text-decoration: none;
    }

    .landing-logo:hover {
      color: #fff;
    }

    .landing-logo img {
      display: block;
      width: 51px;
      height: 47px;
      object-fit: contain;
    }

    .landing-logo span {
      font-weight: 600;
      font-size: 24.504px;
      line-height: normal;
    }

    /* ===== Navbar ===== */
    .landing-navbar {
      position: relative;
      height: 69px;
      overflow: hidden;
      flex-shrink: 0;
    }

    .landing-navbar .landing-container {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      height: 100%;
    }

    .landing-navbar .landing-logo {
      margin-top: 8px;
    }

    .landing-navbar-actions {
      display: flex;
      gap: 12px;
      margin-top: 12px;
      margin-right: 5px;
    }

    /* ===== Hero ===== */
    .landing-hero {
      position: relative;
      flex-shrink: 0;
      min-height: 525px;
      overflow: hidden;
      background: linear-gradient(180deg, #fcfcfd 0%, #f5f4fb 100%);
    }

    .landing-hero-shapes-left {
      position: absolute;
      left: 0;
      bottom: 0;
      width: 560px;
      height: 525px;
      pointer-events: none;
    }

    .landing-hero-media {
      position: absolute;
      top: 0;
      /* Abaixo de 1280px desliza para a direita (o design já corta a foto na borda). */
      right: min(0px, calc((100vw - 1280px) / 2));
      width: 731px;
      height: 525px;
    }

    .landing-hero-media svg {
      display: block;
      width: 100%;
      height: 100%;
    }

    .landing-hero-content {
      padding-top: 118px;
      padding-bottom: 87px;
    }

    .landing-hero-detail {
      width: 50px;
      height: 6px;
      border-radius: 3px;
      background: #810879;
    }

    .landing-hero-title {
      margin: 10.86px 0 0 2.22px;
      font-weight: 800;
      font-size: 68.23px;
      line-height: 78.879px;
      letter-spacing: -.6823px;
      color: #0c0d21;
    }

    .landing-hero-title span {
      display: block;
      color: #820878;
    }

    .landing-hero-description {
      max-width: 501px;
      margin: 7.7px 0 0 4.67px;
      font-weight: 400;
      font-size: 19.498px;
      line-height: 28px;
      color: #26242f;
    }

    .landing-hero-description strong {
      font-weight: 700;
      color: #0e0d18;
    }

    .landing-hero-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 16px;
      margin: 27.7px 0 0 5px;
    }

    /* ===== Footer ===== */
    .landing-footer {
      position: relative;
      flex-grow: 1;
      min-height: 306px;
      overflow: hidden;
      background: #320f3c;
      text-align: center;
    }

    .landing-footer .landing-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 23px 0 41px;
    }

    .landing-footer .landing-logo {
      gap: 4.48px;
      margin-left: 6.6px;
    }

    .landing-footer .landing-logo img {
      width: 57.12px;
      height: 52.64px;
    }

    .landing-footer .landing-logo span {
      font-size: 29.1px;
    }

    .landing-footer-slogan {
      margin: 10.57px 0 0;
      font-weight: 400;
      font-size: 16.452px;
      line-height: normal;
      color: #e8d6ec;
    }

    .landing-footer-partners {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      column-gap: 173px;
      row-gap: 24px;
      margin: 28.3px 0 0 -4px;
    }

    .landing-footer-partner {
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 211px;
    }

    .landing-footer-partner:first-child {
      width: 206px;
    }

    .landing-footer-partner:first-child .landing-footer-partner-label {
      margin-left: 15px;
    }

    .landing-footer-partner:last-child .landing-footer-partner-label {
      margin-right: 22px;
    }

    .landing-footer-partner-label {
      font-family: 'Poppins', system-ui, sans-serif;
      font-weight: 500;
      font-size: 13.225px;
      line-height: 20px;
      letter-spacing: 1.8px;
      text-transform: uppercase;
      color: #f0e2f8;
    }

    .landing-footer-partner-logo {
      display: flex;
      align-items: center;
      justify-content: center;
      height: 58px;
      margin-top: 6px;
    }

    .landing-footer-partner-logo img {
      display: block;
      object-fit: contain;
    }

    .landing-footer-legal {
      margin: 33.2px 0 0;
      font-weight: 500;
      font-size: 10.317px;
      line-height: normal;
      letter-spacing: 1.1px;
      white-space: pre;
      color: #d9c8e0;
    }

    /* ===== Responsivo ===== */
    @media (max-width: 1199.98px) {
      .landing-hero-shapes-left {
        width: 420px;
        height: 394px;
      }

      .landing-hero-content {
        padding-top: 64px;
        padding-bottom: 0;
      }

      .landing-hero-media {
        position: relative;
        right: auto;
        width: min(731px, 100%);
        height: auto;
        margin: 8px 0 0 auto;
        /* Empilhado, o topo do recorte deixa de encostar na navbar: esmaece a borda reta. */
        -webkit-mask-image: linear-gradient(180deg, transparent 0, #000 12%);
        mask-image: linear-gradient(180deg, transparent 0, #000 12%);
      }

      .landing-hero-media svg {
        height: auto;
      }
    }

    @media (max-width: 767.98px) {
      .landing-footer-partners {
        column-gap: 48px;
      }

      .landing-footer-legal {
        white-space: normal;
      }
    }

    @media (max-width: 575.98px) {
      .landing-navbar .landing-btn--contorno-claro {
        display: none;
      }

      .landing-hero-content {
        padding-top: 48px;
      }

      .landing-hero-title {
        font-size: 44px;
        line-height: 52px;
        letter-spacing: -.44px;
      }

      .landing-hero-description {
        font-size: 17px;
        line-height: 26px;
      }

      .landing-hero-actions,
      .landing-hero-title,
      .landing-hero-description {
        margin-left: 0;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .landing-btn {
        transition: none;
      }

      .landing-btn:hover {
        transform: none;
      }
    }
  </style>
</head>

<body>
  <div class="landing">
    <header class="landing-navbar">
      <svg class="landing-bg" viewBox="0 0 1600 69" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <defs>
          <linearGradient id="landing-navbar-fundo" x1="0" y1="0" x2="1600" y2="0" gradientUnits="userSpaceOnUse">
            <stop stop-color="#5E2068" />
            <stop offset="0.1" stop-color="#4E1A5B" />
            <stop offset="0.25" stop-color="#3F1349" />
            <stop offset="1" stop-color="#3E1448" />
          </linearGradient>
          <linearGradient id="landing-navbar-onda-direita" x1="1250" y1="0" x2="1500" y2="0" gradientUnits="userSpaceOnUse">
            <stop stop-color="#BE6ED2" stop-opacity="0" />
            <stop offset="1" stop-color="#BE6ED2" stop-opacity="0.12" />
          </linearGradient>
        </defs>
        <path d="M1600 0H0V69H1600V0Z" fill="url(#landing-navbar-fundo)" />
        <path d="M0 0H60C80 25 100 50 128 69H0V0Z" fill="#BE6ED2" fill-opacity="0.13" />
        <path d="M1250 0H1512C1470 25 1430 50 1388 69H1250V0Z" fill="url(#landing-navbar-onda-direita)" />
      </svg>

      <div class="landing-container">
        <a href="{{ url('/') }}" class="landing-logo" aria-label="Engaja — página inicial">
          <img src="{{ asset('images/landing/engaja-icone.png') }}" alt="">
          <span>Engaja</span>
        </a>

        <nav class="landing-navbar-actions" aria-label="Acesso">
          <a href="{{ route('login') }}" class="landing-btn landing-btn--claro">
            Entrar
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false">
              <path d="M3.75 9H14.25M9.75 13.5L14.25 9L9.75 4.5" stroke="#401648" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </a>
          <a href="{{ route('register') }}" class="landing-btn landing-btn--contorno-claro">Cadastrar</a>
        </nav>
      </div>
    </header>

    <main class="landing-hero">
      <svg class="landing-hero-shapes-left" viewBox="0 0 560 525" preserveAspectRatio="xMinYMax meet" aria-hidden="true" focusable="false">
        <defs>
          <linearGradient id="landing-hero-forma-esquerda" x1="0" y1="213" x2="300" y2="525" gradientUnits="userSpaceOnUse">
            <stop stop-color="#E9E4FB" />
            <stop offset="1" stop-color="#EEEBFB" />
          </linearGradient>
        </defs>
        <path d="M0 128C90 98 225 98 282 150C340 205 430 300 470 400C500 460 530 500 560 525H0V128Z" fill="#F3F3FC" />
        <path d="M0 213C9.7 220.2 23.4 221.2 34 227.8C39.2 231.1 44.9 233.2 51 234C59.6 239.5 65.9 248.2 74.9 254C99.1 272.9 119.9 295.7 138.5 320C174.8 365 204.1 416.6 250 453C275 485 320 515 370 525H0V213Z" fill="url(#landing-hero-forma-esquerda)" />
      </svg>

      <div class="landing-container landing-hero-content">
        <div class="landing-hero-detail" aria-hidden="true"></div>
        <h1 class="landing-hero-title">Bem-vindo ao <span>Engaja</span></h1>
        <p class="landing-hero-description">
          Sistema de <strong>Gestão de Inscrições e Presenças</strong> para fortalecer o engajamento educacional.
        </p>
        <div class="landing-hero-actions">
          <a href="{{ route('login') }}" class="landing-btn landing-btn--escuro">
            Entrar
            <svg width="21" height="21" viewBox="0 0 21 21" fill="none" aria-hidden="true" focusable="false">
              <path d="M4.375 10.5H16.625M11.375 15.75L16.625 10.5L11.375 5.25" stroke="white" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </a>
          <a href="{{ route('register') }}" class="landing-btn landing-btn--contorno">Cadastrar</a>
        </div>
      </div>

      {{-- Recorte direito do design (x 869–1600): formas, foto com máscara orgânica e bolinha. --}}
      <div class="landing-hero-media">
        <svg width="731" height="525" viewBox="869 0 731 525" role="img" aria-label="Pessoas participando de uma formação" focusable="false">
          <defs>
            <linearGradient id="landing-hero-forma-foto" x1="1000" y1="300" x2="1560" y2="525" gradientUnits="userSpaceOnUse">
              <stop stop-color="#F1F0FC" />
              <stop offset="0.55" stop-color="#F2F1FC" />
              <stop offset="1" stop-color="#F2F1FC" stop-opacity="0" />
            </linearGradient>
            <linearGradient id="landing-hero-forma-inferior" x1="900" y1="0" x2="1560" y2="0" gradientUnits="userSpaceOnUse">
              <stop stop-color="#F0EFFC" />
              <stop offset="0.5" stop-color="#F1F0FC" />
              <stop offset="1" stop-color="#F1F0FC" stop-opacity="0" />
            </linearGradient>
            <clipPath id="landing-hero-mascara-foto">
              <path transform="translate(951.2147 60.7437)" d="M387.785 0.756328C347.485 5.05633 312.385 28.5563 284.885 57.2563C253.585 88.8563 231.185 128.956 194.785 155.456C164.485 177.856 125.485 180.756 91.7853 195.756C54.0853 211.056 21.4853 240.656 7.18535 279.256C-6.41465 313.456 -0.614653 354.356 23.7853 382.256C47.7853 411.856 86.5853 429.756 124.785 426.056C194.585 416.656 256.885 368.856 329.785 377.956C399.085 386.356 463.185 427.356 534.785 417.456C570.885 413.756 605.285 401.156 637.785 385.656C647.385 382.256 659.785 370.956 659.785 363.256V134.356C625.685 123.356 607.885 114.456 591.785 102.956C527.585 63.6563 470.685 -8.14367 387.785 0.756328Z" />
            </clipPath>
          </defs>
          <path d="M1097 0C1045 38 955 125 913.8 202C881.8 262 861.1 330.7 873 399C871 450 872 500 873 525H1600V0H1097Z" fill="url(#landing-hero-forma-foto)" />
          <path d="M941 381C904.1 410.5 866 452.4 876 504C874 512 873 520 873 525H1600V381H941Z" fill="url(#landing-hero-forma-inferior)" />
          <path d="M1178.5 0C1136.3 55.1 1121.1 124.5 1113 192C1108.9 225.4 1102.5 259.8 1083 288C1050.8 335.3 996.7 361.1 945.3 382H1200L1600 331V0H1178.5Z" fill="#E7E4F8" />
          <image href="{{ asset('images/landing/hero-foto.jpg') }}" x="951" y="61" width="649" height="427" preserveAspectRatio="xMidYMid slice" clip-path="url(#landing-hero-mascara-foto)" />
          <circle cx="1526" cy="81" r="12" fill="#B9A1EF" />
        </svg>
      </div>
    </main>

    <footer class="landing-footer">
      <svg class="landing-bg" viewBox="0 0 1600 306" preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <defs>
          <linearGradient id="landing-footer-fundo" x1="800" y1="0" x2="800" y2="306" gradientUnits="userSpaceOnUse">
            <stop stop-color="#621F6E" />
            <stop offset="1" stop-color="#320F3C" />
          </linearGradient>
          <linearGradient id="landing-footer-clareamento" x1="0" y1="153" x2="1600" y2="153" gradientUnits="userSpaceOnUse">
            <stop stop-color="#BE6ED2" stop-opacity="0.087" />
            <stop offset="0.15" stop-color="#BE6ED2" stop-opacity="0.044" />
            <stop offset="0.3" stop-color="#BE6ED2" stop-opacity="0.016" />
            <stop offset="0.5" stop-color="#BE6ED2" stop-opacity="0" />
            <stop offset="0.7" stop-color="#BE6ED2" stop-opacity="0.016" />
            <stop offset="0.85" stop-color="#BE6ED2" stop-opacity="0.044" />
            <stop offset="1" stop-color="#BE6ED2" stop-opacity="0.087" />
          </linearGradient>
        </defs>
        <rect width="1600" height="306" fill="url(#landing-footer-fundo)" />
        <rect width="1600" height="306" fill="url(#landing-footer-clareamento)" />
        <path d="M0 40C41.2987 45.3868 79.3735 54.9644 116.019 67.4951C78.3347 69.9686 39.715 74.0427 0 80V40Z" fill="#BE6ED2" fill-opacity="0.117647" />
        <path d="M1180 0C1351.28 38.0618 1441.07 103.285 1578.6 126.748C1502.13 150.278 1429.81 166.191 1360.72 176.135C1209.18 119.76 1092.34 48.0842 900 0H1180Z" fill="#BE6ED2" fill-opacity="0.117647" />
        <path d="M1600 120C1592.83 122.316 1585.7 124.564 1578.6 126.748C1441.07 103.285 1351.28 38.0618 1180 0H1600V120Z" fill="#BE6ED2" fill-opacity="0.109804" />
        <path d="M0 80C600 -9.99997 950 330 1600 120V306H0V80Z" fill="#180622" fill-opacity="0.235294" />
      </svg>

      <div class="landing-container">
        <a href="{{ url('/') }}" class="landing-logo" aria-label="Engaja — página inicial">
          <img src="{{ asset('images/landing/engaja-icone.png') }}" alt="">
          <span>Engaja</span>
        </a>

        <p class="landing-footer-slogan">Sistema de Gestão de Participação e Engajamento.</p>

        <div class="landing-footer-partners">
          <div class="landing-footer-partner">
            <span class="landing-footer-partner-label">Realização</span>
            <div class="landing-footer-partner-logo">
              <img src="{{ asset('images/landing/instituto-paulo-freire.png') }}" alt="Instituto Paulo Freire" width="206" height="50">
            </div>
          </div>
          <div class="landing-footer-partner">
            <span class="landing-footer-partner-label">Parceria</span>
            <div class="landing-footer-partner-logo">
              <img src="{{ asset('images/landing/petrobras.png') }}" alt="Petrobras" width="211" height="58">
            </div>
          </div>
        </div>

        <p class="landing-footer-legal">INSTITUTO DE EDUCAÇÃO E DIREITOS HUMANOS PAULO FREIRE   |   CNPJ 04.950.603/0001-05</p>
      </div>
    </footer>
  </div>
</body>

</html>
