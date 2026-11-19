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
      --landing-container: 1200px;
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

    /* ===== Hero ===== */
    .landing-hero {
      position: relative;
      flex-shrink: 0;
      overflow: hidden;
      background: linear-gradient(180deg, #fcfcfd 0%, #f5f4fb 100%);
    }

    .landing-hero-shapes-left,
    .landing-hero-shapes-right {
      position: absolute;
      bottom: 0;
      pointer-events: none;
    }

    .landing-hero-shapes-left {
      left: 0;
      width: min(560px, 45%);
      height: auto;
      max-height: 100%;
    }

    /* Mantém a proporção do desenho: a curva da esquerda nunca é cortada */
    .landing-hero-shapes-right {
      top: 0;
      right: 0;
      height: 100%;
      width: auto;
      aspect-ratio: 731 / 525;
    }

    /* Transição suave do hero para a seção de cards */
    .landing-hero::after {
      content: "";
      position: absolute;
      inset: auto 0 0;
      height: 72px;
      background: linear-gradient(180deg, rgba(245, 244, 251, 0) 0%, #f5f4fb 100%);
      pointer-events: none;
    }

    /* Texto à esquerda e foto no centro-direita, no mesmo container */
    .landing-hero-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr);
      align-items: center;
      gap: clamp(24px, 4vw, 64px);
      /* mais respiro em cima que embaixo: o conteúdo fica próximo dos cards */
      padding-block: 104px 8px;
    }

    .landing-hero-media svg {
      display: block;
      width: 100%;
      max-width: 640px;
      height: auto;
      margin-left: auto;
      overflow: visible;
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
      letter-spacing: -.01em;
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

    /* ===== Recursos (cards) ===== */
    /* Ocupa o espaço que sobra em telas altas — o rodapé mantém a altura do design. */
    .landing-features {
      flex: 1 0 auto;
      display: flex;
      align-items: center;
      padding-block: 16px 56px;
      background: #f5f4fb;
    }

    .landing-features .landing-container {
      width: min(var(--landing-container), 100% - 32px);
    }

    .landing-features-title {
      margin: 0 0 20px;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #820878;
    }

    .landing-features-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: clamp(16px, 2vw, 24px);
    }

    /* Mesmo vocabulário dos cards internos (cores --stat-accent/--stat-soft e
       .eg-stat-icon vêm de sass/ui/_stat-card.scss) */
    .landing-feature {
      position: relative;
      overflow: hidden;
      padding: 24px 24px 26px;
      background: #fff;
      border: 1px solid #ece7ee;
      border-radius: 1.1rem;
      box-shadow: 0 4px 24px rgba(66, 25, 68, .08), 0 1px 4px rgba(0, 0, 0, .04);
      transition: transform .15s ease, box-shadow .2s ease;
    }

    .landing-feature::before {
      content: "";
      position: absolute;
      inset: 0 0 auto;
      height: 3px;
      background: var(--stat-accent, #421944);
    }

    .landing-feature:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(66, 25, 68, .13);
    }

    .landing-feature .eg-stat-icon {
      margin-bottom: 14px;
    }

    .landing-feature-title {
      margin: 0 0 6px;
      font-size: 17px;
      font-weight: 700;
      color: #1f2937;
    }

    .landing-feature-text {
      margin: 0;
      font-size: 14.5px;
      line-height: 1.55;
      color: #6b7280;
    }

    /* ===== Responsivo ===== */
    /* O título muda de tamanho em degraus (não de forma fluida). */
    @media (max-width: 1199.98px) {
      .landing-hero-title {
        font-size: 56px;
        line-height: 64px;
      }
    }

    /* Empilhado: só o texto — a foto e as formas que a acompanham saem de cena. */
    @media (max-width: 991.98px) {
      .landing-hero-grid {
        grid-template-columns: minmax(0, 1fr);
        padding-block: 56px 32px;
      }

      .landing-hero-media,
      .landing-hero-shapes-right {
        display: none;
      }

      .landing-hero-title {
        font-size: 52px;
        line-height: 60px;
      }
    }

    @media (max-width: 767.98px) {
      .landing-features-grid {
        grid-template-columns: minmax(0, 1fr);
      }
    }

    @media (max-width: 575.98px) {
      .landing-hero-grid {
        padding-block: 48px 24px;
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
    <x-site-header />

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

      <svg class="landing-hero-shapes-right" viewBox="869 0 731 525" preserveAspectRatio="xMaxYMid meet" aria-hidden="true" focusable="false">
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
        </defs>
        <path d="M1097 0C1045 38 955 125 913.8 202C881.8 262 861.1 330.7 873 399C871 450 872 500 873 525H1600V0H1097Z" fill="url(#landing-hero-forma-foto)" />
        <path d="M941 381C904.1 410.5 866 452.4 876 504C874 512 873 520 873 525H1600V381H941Z" fill="url(#landing-hero-forma-inferior)" />
      </svg>

      <div class="landing-container landing-hero-grid">
        <div class="landing-hero-content">
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

        {{-- Foto: SVG próprio com a forma orgânica completa (máscara) e a imagem dentro dela. --}}
        <div class="landing-hero-media">
          <svg viewBox="-10 -24 744 460" role="img" aria-label="Pessoas participando de uma formação" focusable="false">
            <defs>
              <mask id="landing-foto-mascara" maskUnits="userSpaceOnUse" x="-10" y="-24" width="744" height="460">
                <path d="M387.785 0.756C347.485 5.056 312.385 28.556 284.885 57.256C253.585 88.856 231.185 128.956 194.785 155.456C164.485 177.856 125.485 180.756 91.785 195.756C54.085 211.056 21.485 240.656 7.185 279.256C-6.415 313.456 -0.615 354.356 23.785 382.256C47.785 411.856 86.585 429.756 124.785 426.056C194.585 416.656 256.885 368.856 329.785 377.956C399.085 386.356 463.185 427.356 534.785 417.456C570.885 413.756 605.285 401.156 637.785 385.656C663.8 373.3 698 335 700 270C702 205 678 158 645 133C625 118 607 112 591.785 102.956C527.585 63.656 470.685 -8.144 387.785 0.756Z" fill="#fff" />
              </mask>
            </defs>
            <path d="M387.785 0.756C347.485 5.056 312.385 28.556 284.885 57.256C253.585 88.856 231.185 128.956 194.785 155.456C164.485 177.856 125.485 180.756 91.785 195.756C54.085 211.056 21.485 240.656 7.185 279.256C-6.415 313.456 -0.615 354.356 23.785 382.256C47.785 411.856 86.585 429.756 124.785 426.056C194.585 416.656 256.885 368.856 329.785 377.956C399.085 386.356 463.185 427.356 534.785 417.456C570.885 413.756 605.285 401.156 637.785 385.656C663.8 373.3 698 335 700 270C702 205 678 158 645 133C625 118 607 112 591.785 102.956C527.585 63.656 470.685 -8.144 387.785 0.756Z" transform="translate(22 -18)" fill="#E7E4F8" />
            <image href="{{ asset('images/landing/hero-foto.jpg') }}" x="-8" y="-2" width="716" height="434" preserveAspectRatio="xMidYMid slice" mask="url(#landing-foto-mascara)" />
            <circle cx="702" cy="-6" r="11" fill="#B9A1EF" />
          </svg>
        </div>
      </div>
    </main>

    <section class="landing-features" aria-labelledby="landing-features-title">
      <div class="landing-container">
        <h2 id="landing-features-title" class="landing-features-title">O que você encontra no Engaja</h2>
        <div class="landing-features-grid">
          <article class="landing-feature eg-stat-card--purple">
            <span class="eg-stat-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
            <h3 class="landing-feature-title">Ações pedagógicas</h3>
            <p class="landing-feature-text">Organize e acompanhe inscrições para formações, encontros e reuniões.</p>
          </article>
          <article class="landing-feature eg-stat-card--green">
            <span class="eg-stat-icon"><i class="bi bi-qr-code-scan" aria-hidden="true"></i></span>
            <h3 class="landing-feature-title">Presença</h3>
            <p class="landing-feature-text">Controle a participação com check-in via QR Code ou registro manual.</p>
          </article>
          <article class="landing-feature eg-stat-card--orange">
            <span class="eg-stat-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
            <h3 class="landing-feature-title">Relatórios</h3>
            <p class="landing-feature-text">Visualize indicadores de engajamento por ação pedagógica, município ou região.</p>
          </article>
        </div>
      </div>
    </section>

    <x-site-footer />
  </div>
</body>

</html>
