<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ config('app.name', 'Engaja') }}</title>
  <link rel="icon" type="image/png" href="{{ asset('images/engaja-favicon.png') }}">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@500&display=swap" rel="stylesheet">

  @vite(['resources/sass/app.scss', 'resources/js/app.js'])

  <style>
    :root {
      --engaja-purple: #421944;
    }

    body {
      font-family: 'Montserrat', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    }

    .navbar-brand {
      font-weight: 700;
      letter-spacing: .2px;
    }
  </style>
  <style>
    .form-control {
      border-color: #b1b6bbff !important;
      /* cinza escuro padrão Bootstrap */
    }

    .form-control:focus {
      border-color: #421944 !important;
      /* roxo Engaja no foco */
      box-shadow: 0 0 0 0.2rem rgba(66, 25, 68, 0.25);
      /* glow roxo no foco */
    }

    .form-select {
      border-color: #b1b6bbff !important;
    }

    .form-select:focus {
      border-color: #421944 !important;
      box-shadow: 0 0 0 0.2rem rgba(66, 25, 68, 0.25);
    }
  </style>
  <style>
    .admin-shell {
      min-height: 100vh;
      background: #f6f7fb;
      transition: all .2s ease;
    }

    .admin-sidebar {
      flex: 0 0 auto;
      width: max-content;
      min-width: 300px;
      background: linear-gradient(180deg, #421944 0%, #2c1230 100%);
      color: #f5f3ff;
      min-height: 100vh;
      height: 100vh;
      position: sticky;
      top: 0;
      padding: 1.5rem 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
      z-index: 1030;
      transition: transform .3s ease;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
    }

    .admin-sidebar__brand {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      padding: 0 .25rem;
    }

    .admin-sidebar__actions {
      display: flex;
      align-items: center;
      gap: .4rem;
    }

    .admin-collapse-btn {
      border-radius: .9rem;
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: #fff;
      background: rgba(255, 255, 255, 0.08);
      padding: .35rem .55rem;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .admin-collapse-btn:hover {
      color: #fff;
      background: rgba(255, 255, 255, 0.16);
    }

    .admin-sidebar__brand {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
    }

    .admin-sidebar__logo {
      height: 38px;
    }

    .admin-sidebar__logo-mini {
      display: none;
      height: 32px;
    }

    .admin-sidebar__section {
      display: grid;
      gap: .35rem;
      padding: .5rem 0 1rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .admin-sidebar__section:last-of-type {
      border-bottom: none;
      padding-bottom: 0;
    }

    .admin-sidebar__label {
      text-transform: uppercase;
      letter-spacing: .6px;
      color: rgba(255, 255, 255, 0.55);
      font-size: .75rem;
      margin-bottom: .35rem;
      font-weight: 700;
    }

    .admin-nav-link {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .65rem .75rem;
      color: #f5f3ff;
      text-decoration: none;
      border-radius: .9rem;
      transition: all .2s ease;
      font-weight: 600;
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
      overflow: visible;
    }

    .admin-nav-link.btn {
      border: none;
      background: transparent;
      text-align: left;
    }

    .admin-nav-link:hover {
      background: rgba(255, 255, 255, 0.08);
      color: #fff;
    }

    .admin-nav-link.active {
      background: #f8f7fb;
      color: #2c1230;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    }

    .admin-nav-icon {
      width: 36px;
      height: 36px;
      border-radius: .9rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.12);
      font-weight: 700;
      font-size: .8rem;
      letter-spacing: .3px;
      flex-shrink: 0;
      color: inherit;
    }

    .admin-nav-link.active .admin-nav-icon {
      background: linear-gradient(135deg, #421944, #62305f);
      color: #fff;
    }

    .admin-nav-text {
      flex: 0 0 auto;
      white-space: nowrap;
      line-height: 1.2;
    }

    .admin-sidebar__accordion .accordion-item {
      background: transparent;
      border: 0;
    }

    .admin-sidebar__accordion .accordion-item + .accordion-item {
      margin-top: .35rem;
    }

    .admin-sidebar__accordion .accordion-button {
      background: transparent;
      color: #f5f3ff;
      padding: .6rem .75rem;
      border-radius: .9rem;
      gap: .75rem;
      box-shadow: none;
      font-weight: 700;
    }

    .admin-sidebar__accordion .accordion-button::after {
      filter: brightness(0) invert(1);
    }

    .admin-sidebar__accordion .accordion-button:not(.collapsed) {
      background: rgba(255, 255, 255, 0.12);
      color: #fff;
    }

    .admin-sidebar__accordion .accordion-button:focus {
      box-shadow: 0 0 0 .15rem rgba(255, 255, 255, 0.15);
    }

    .admin-sidebar__accordion .accordion-body {
      padding: .35rem 0 .6rem;
      display: grid;
      gap: .25rem;
    }

    .admin-sidebar__accordion .admin-nav-link {
      padding-left: 2.7rem;
    }

    .admin-sidebar__accordion .admin-subsection__label {
      text-transform: uppercase;
      letter-spacing: .4px;
      color: rgba(255, 255, 255, 0.6);
      font-size: .7rem;
      padding: .35rem .75rem .15rem;
      font-weight: 700;
    }

    /* Largura fixa da sidebar: os submenus fechados continuam no cálculo do
       width: max-content (altura zero e invisíveis, em vez de display: none),
       então a sidebar tem sempre a largura do maior item do menu, aberto ou não. */
    .admin-sidebar__accordion .accordion-collapse.collapse:not(.show) {
      display: block;
      height: 0;
      overflow: hidden;
      visibility: hidden;
    }

    .admin-sidebar__account {
      margin-top: auto;
    }

    .admin-topbar {
      background: #ffffff;
      border-bottom: 1px solid #e7e8ed;
      padding: 1rem 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      position: sticky;
      top: 0;
      z-index: 1010;
      box-sizing: border-box;
      width: 100%;
    }

    .admin-topbar__actions {
      display: flex;
      align-items: center;
      gap: .75rem;
    }

    .admin-topbar__title {
      font-size: 1rem;
      margin: 0;
    }

    .admin-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: #421944;
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      user-select: none;
      -webkit-user-select: none;
    }

    .admin-avatar--image {
      object-fit: cover;
      background: #fff;
      border: 1px solid #e7e8ed;
    }

    .admin-main {
      flex: 1 1 auto;
      display: flex;
      flex-direction: column;
    }

    .admin-content {
      flex: 1 1 auto;
      display: flex;
      flex-direction: column;
      overflow-x: hidden;
    }

    .admin-main {
      width: 100%;
      overflow-x: hidden;
    }

    /* Páginas públicas (login, cadastro…): mesmo fundo lilás claro da landing */
    .guest-shell {
      background: #f5f4fb;
    }

    /* Conteúdo alinhado ao header institucional (1200px úteis, 16px de respiro) */
    .guest-shell main > .container {
      max-width: 1232px;
      padding-inline: 16px;
    }

    /* Páginas com .auth-center (ex.: login): conteúdo centralizado na vertical
       no espaço entre o header e o rodapé */
    .guest-shell main:has(.auth-center) {
      display: flex;
      flex-direction: column;
    }

    .guest-shell main:has(.auth-center) > .container {
      display: flex;
      flex: 1 0 auto;
      flex-direction: column;
    }

    .auth-center {
      flex: 1 0 auto;
      align-content: center;
    }

    /* sem a margem do rodapé, o respiro fica igual acima e abaixo do card */
    .guest-shell main:has(.auth-center) + .site-footer {
      margin-top: 0 !important;
    }

    .admin-page-header {
      background: #fff;
      border: 1px solid #e7e8ed;
      border-radius: 1rem;
      padding: 1rem 1.25rem;
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.06);
    }

    .admin-sidebar-backdrop {
      display: none;
    }

    .admin-shell.is-collapsed .admin-sidebar {
      width: 86px;
      min-width: 0;
      max-width: 86px;
      flex: 0 0 86px;
      box-sizing: border-box;
      align-items: center;
      padding-left: 0.45rem;
      padding-right: 0.45rem;
      overflow-x: hidden;
    }

    .admin-shell.is-collapsed .admin-sidebar__section {
      justify-items: center;
    }

    .admin-shell.is-collapsed .admin-nav-text,
    .admin-shell.is-collapsed .admin-sidebar__label,
    .admin-shell.is-collapsed .admin-sidebar__brand div.lh-sm {
      display: none;
    }

    .admin-shell.is-collapsed .admin-sidebar__logo {
      height: 34px;
    }

    .admin-shell.is-collapsed .admin-topbar {
      padding-left: 1rem;
    }

    .admin-shell.is-collapsed .admin-sidebar__logo-main {
      display: none;
    }

    .admin-shell.is-collapsed .admin-sidebar__logo-mini {
      display: block;
    }

    .admin-shell.is-collapsed .admin-sidebar__brand {
      justify-content: center;
      padding: 0;
    }

    .admin-shell.is-collapsed .admin-sidebar__brand a {
      justify-content: center;
      gap: 0;
    }

    .admin-shell.is-collapsed .admin-nav-link {
      padding: .55rem .35rem;
      justify-content: center;
      width: auto;
      max-width: 100%;
    }

    .admin-shell.is-collapsed .admin-sidebar form {
      display: flex;
      justify-content: center;
      width: 100%;
    }

    .admin-shell.is-collapsed .admin-nav-link.btn.w-100 {
      width: auto !important;
    }

    .admin-shell.is-collapsed .admin-nav-icon {
      margin: 0;
      width: 38px;
      height: 38px;
    }

    .admin-shell.is-collapsed .admin-nav-link.active {
      box-shadow: none;
    }

    .admin-shell.is-collapsed .admin-sidebar__accordion .accordion-button::after {
      display: none;
    }

    .admin-shell.is-collapsed .admin-sidebar__accordion .accordion-collapse {
      display: none;
    }

    @media (max-width: 991.98px) {
      .admin-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        transform: translateX(-105%);
        width: 260px;
      }

      .admin-topbar {
        padding: .85rem 1rem;
      }

      .admin-sidebar.is-open {
        transform: translateX(0);
      }

      .admin-sidebar-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        opacity: 0;
        visibility: hidden;
        transition: opacity .3s ease;
        z-index: 1020;
      }

      .admin-sidebar-backdrop.show {
        opacity: 1;
        visibility: visible;
      }
    }

    body.sidebar-open {
      overflow: hidden;
    }
  </style>
  @stack('styles')
  @livewireStyles
</head>

@php($useSidebar = auth()->check())
<body class="{{ $useSidebar ? 'min-vh-100 bg-light' : 'd-flex flex-column min-vh-100 guest-shell' }}">
  @if($useSidebar)
    <div class="admin-shell d-flex">
      @include('layouts.partials.admin-sidebar')
      <div class="admin-sidebar-backdrop" id="adminSidebarBackdrop"></div>
      <div class="admin-main">
        <header class="admin-topbar shadow-sm">
          <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-primary d-lg-none" type="button" id="sidebarToggle" aria-label="Abrir menu lateral">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M1.5 3.75A.75.75 0 0 1 2.25 3h11.5a.75.75 0 0 1 0 1.5H2.25a.75.75 0 0 1-.75-.75m0 4A.75.75 0 0 1 2.25 7h11.5a.75.75 0 0 1 0 1.5H2.25a.75.75 0 0 1-.75-.75m0 4A.75.75 0 0 1 2.25 11h11.5a.75.75 0 0 1 0 1.5H2.25a.75.75 0 0 1-.75-.75" />
              </svg>
            </button>
            <div>
              <div class="text-uppercase text-muted small fw-semibold mb-0">Área interna</div>
              <p class="admin-topbar__title fw-bold mb-0">Painel Engaja</p>
            </div>
          </div>
            <div class="admin-topbar__actions">
            @php($hasQuickActions = false)
            <div class="dropdown">
              <button class="btn btn-engaja dropdown-toggle" type="button" id="quickActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                  Ações rápidas
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="quickActionsDropdown">
                @hasanyrole('administrador|gerente|eq_pedagogica')
                  @php($hasQuickActions = true)
                    <li><a class="dropdown-item" href="{{ route('eventos.create') }}">Nova ação pedagógica</a></li>
                @endhasanyrole
                @hasanyrole('administrador|gerente|eq_pedagogica|articulador|SME')
                  @php($hasQuickActions = true)
                  <li><a class="dropdown-item" href="{{ route('agendamentos.create') }}">Novo agendamento</a></li>
                @endhasanyrole
                @hasanyrole('administrador|gerente|eq_pedagogica|articulador')
                  @php($hasQuickActions = true)
                    <li><a class="dropdown-item" href="{{ route('avaliacoes.create') }}">Nova avaliação</a></li>
                    <li><a class="dropdown-item" href="{{ route('usuarios.create') }}">Cadastrar usuário</a></li>
                @endhasanyrole
                @hasanyrole('administrador|gerente')
                  @php($hasQuickActions = true)
                  <li><a class="dropdown-item" href="{{ route('certificados.modelos.create') }}">Novo modelo de certificado</a></li>
                @endhasanyrole
                @unless($hasQuickActions)
                    <li><span class="dropdown-item-text text-muted">Nenhuma ação disponível</span></li>
                @endunless
              </ul>
            </div>
            <span class="text-muted small d-none d-md-inline">Ola, {{ Auth::user()->name }}</span>
            <div class="dropdown">
              <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                @if (Auth::user()->profile_photo_url)
                  <img src="{{ Auth::user()->profile_photo_url }}" alt="Foto de perfil de {{ Auth::user()->name }}" class="admin-avatar admin-avatar--image">
                @else
                  <span class="admin-avatar">{{ Auth::user()->profile_initial }}</span>
                @endif
                <span class="d-none d-sm-inline">{{ Auth::user()->name }}</span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Meu perfil</a></li>
                <li><a class="dropdown-item" href="{{ route('profile.certificados') }}">Meus certificados</a></li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li>
                  <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item">Sair</button>
                  </form>
                </li>
              </ul>
            </div>
          </div>
        </header>

        <div class="admin-content">
          @include('layouts.partials.flash', ['containerClass' => 'container-fluid px-4 px-lg-5'])

          @isset($header)
            <div class="container-fluid px-4 px-lg-5 mt-3">
              <div class="admin-page-header">
                {{ $header }}
              </div>
            </div>
          @endisset

          <main class="flex-grow-1 py-4">
            <div class="container-fluid px-4 px-lg-5">
              @isset($slot) {{ $slot }} @else @yield('content') @endisset
            </div>
          </main>

          @include('layouts.footer')
        </div>
      </div>
    </div>
  @else
    <div class="d-flex flex-column min-vh-100">
      <x-site-header />

      @isset($header)
        <header class="bg-white border-bottom py-3">
          <div class="container">{{ $header }}</div>
        </header>
      @endisset


      @include('layouts.partials.flash')

      <main class="flex-grow-1 py-4">
        <div class="container">
          @isset($slot) {{ $slot }} @else @yield('content') @endisset
        </div>
      </main>

      @include('layouts.footer') {{-- <footer class="bg-primary border-top mt-auto pt-5"> ... --}}

    </div>
  @endif

  @stack('scripts')
  @if($useSidebar)
    <script>
      (() => {
        const sidebar = document.getElementById('adminSidebar');
        const backdrop = document.getElementById('adminSidebarBackdrop');
        const toggle = document.getElementById('sidebarToggle');
        const close = document.getElementById('sidebarClose');
        const collapseTopbarBtn = document.getElementById('sidebarCollapseToggle');
        const shell = document.querySelector('.admin-shell');

        const closeSidebar = () => {
          sidebar?.classList.remove('is-open');
          backdrop?.classList.remove('show');
          document.body.classList.remove('sidebar-open');
        };

        const openSidebar = () => {
          // em mobile sempre abre expandido
          if (window.innerWidth < 992) {
            shell?.classList.remove('is-collapsed');
          }
          sidebar?.classList.add('is-open');
          backdrop?.classList.add('show');
          document.body.classList.add('sidebar-open');
        };

        const toggleCollapsed = () => {
          shell?.classList.toggle('is-collapsed');
        };

        toggle?.addEventListener('click', (event) => {
          event.preventDefault();
          if (sidebar?.classList.contains('is-open')) {
            closeSidebar();
          } else {
            openSidebar();
          }
        });

        close?.addEventListener('click', closeSidebar);
        backdrop?.addEventListener('click', closeSidebar);
        collapseTopbarBtn?.addEventListener('click', toggleCollapsed);

        window.addEventListener('resize', () => {
          if (window.innerWidth >= 992) {
            closeSidebar();
          } else {
            // ao entrar em mobile, garantir que nao inicie colapsado
            shell?.classList.remove('is-collapsed');
          }
        });
      })();
    </script>
  @endif

  <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <x-ui.modal-header icon="question-circle" title="Confirmar ação" title-id="confirmModalLabel" />
        <div class="modal-body">
          <p class="mb-0 js-confirm-message"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-engaja js-confirm-accept">Confirmar</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="blockedDeleteModal" tabindex="-1" aria-labelledby="blockedDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content shadow-lg border-0">
        <x-ui.modal-header warning icon="exclamation-triangle" title="Exclusão bloqueada" title-id="blockedDeleteModalLabel" />
        <div class="modal-body">
          <p class="fw-semibold text-dark mb-2 js-blocked-delete-title">Não é possível excluir este momento.</p>
          <p class="mb-0 text-muted js-blocked-delete-message">
            Este momento possui presenças associadas e não pode ser excluído. Para excluí-lo, é necessário remover as presenças antes.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-engaja" data-bs-dismiss="modal">Entendi</button>
        </div>
      </div>
    </div>
  </div>
  
  @if (empty($exibirModalCompletarPerfil) && (!empty($showProfilePhotoPromptModal) || $errors->getBag('photoPrompt')->isNotEmpty()))
    @include('layouts.partials.profile-photo-prompt-modal')
  @endif
  
  @livewireScripts
</body>

  @if (!empty($exibirModalCompletarPerfil))
  <div class="modal fade"
       id="modalCompletarPerfil"
       tabindex="-1"
       aria-labelledby="modalCompletarPerfilLabel"
       aria-modal="true"
       role="dialog"
       data-bs-backdrop="static"
       data-bs-keyboard="false">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
          <div class="modal-content">

              {{-- sem botão de fechar: o preenchimento é obrigatório --}}
              <x-ui.modal-header icon="clipboard-data" title="Complete seu perfil demográfico" title-id="modalCompletarPerfilLabel" :dismissible="false" />

              <div class="modal-body">
                  <p class="text-muted mb-4">
                      Para continuar usando o Engaja, precisamos de algumas informações demográficas.
                      Esses dados são utilizados apenas para fins estatísticos e de políticas públicas.
                  </p>

                  @if ($errors->any())
                  <div class="alert alert-danger">
                      <ul class="mb-0">
                          @foreach ($errors->all() as $error)
                          <li>{{ $error }}</li>
                          @endforeach
                      </ul>
                  </div>
                  @endif

                  <form method="POST" action="{{ route('profile.complete-demographics') }}" id="form-demograficos">
                      @csrf

                      {{-- 1. Identidade de Gênero --}}
                      <div class="mb-3">
                          <label for="identidade_genero_modal" class="form-label fw-semibold">
                              Identidade de Gênero <span class="text-danger">*</span>
                          </label>
                          <select name="identidade_genero" id="identidade_genero_modal"
                                  class="form-select @error('identidade_genero') is-invalid @enderror"
                                  required onchange="toggleOutro(this, 'identidade_genero_outro_wrap_modal')">
                              <option value="" disabled selected>Selecione...</option>
                              <option value="Mulher Cisgênero"   {{ old('identidade_genero') == 'Mulher Cisgênero'   ? 'selected' : '' }}>Mulher Cisgênero</option>
                              <option value="Mulher Transsexual" {{ old('identidade_genero') == 'Mulher Transsexual' ? 'selected' : '' }}>Mulher Transsexual</option>
                              <option value="Homem Cisgênero"    {{ old('identidade_genero') == 'Homem Cisgênero'    ? 'selected' : '' }}>Homem Cisgênero</option>
                              <option value="Homem Transsexual"  {{ old('identidade_genero') == 'Homem Transsexual'  ? 'selected' : '' }}>Homem Transsexual</option>
                              <option value="Travesti"           {{ old('identidade_genero') == 'Travesti'           ? 'selected' : '' }}>Travesti</option>
                              <option value="Não binárie"        {{ old('identidade_genero') == 'Não binárie'        ? 'selected' : '' }}>Não binárie</option>
                              <option value="Prefiro não responder" {{ old('identidade_genero') == 'Prefiro não responder' ? 'selected' : '' }}>Prefiro não responder</option>
                              <option value="Outro"              {{ old('identidade_genero') == 'Outro'              ? 'selected' : '' }}>Outro</option>
                          </select>
                          @error('identidade_genero')
                          <div class="invalid-feedback">{{ $message }}</div>
                          @enderror
                          <div id="identidade_genero_outro_wrap_modal" class="mt-2" style="display:none">
                              <input type="text" name="identidade_genero_outro"
                                     class="form-control @error('identidade_genero_outro') is-invalid @enderror"
                                     placeholder="Especifique sua identidade de gênero"
                                     value="{{ old('identidade_genero_outro') }}">
                              @error('identidade_genero_outro')
                              <div class="invalid-feedback">{{ $message }}</div>
                              @enderror
                          </div>
                      </div>

                      {{-- 2. Raça / Cor --}}
                      <div class="mb-3">
                          <label for="raca_cor_modal" class="form-label fw-semibold">
                              Raça / Cor <span class="text-danger">*</span>
                          </label>
                          <select name="raca_cor" id="raca_cor_modal"
                                  class="form-select @error('raca_cor') is-invalid @enderror"
                                  required>
                              <option value="" disabled selected>Selecione...</option>
                              <option value="Preta"                {{ old('raca_cor') == 'Preta'                ? 'selected' : '' }}>Preta</option>
                              <option value="Parda"                {{ old('raca_cor') == 'Parda'                ? 'selected' : '' }}>Parda</option>
                              <option value="Branca"               {{ old('raca_cor') == 'Branca'               ? 'selected' : '' }}>Branca</option>
                              <option value="Amarela"              {{ old('raca_cor') == 'Amarela'              ? 'selected' : '' }}>Amarela</option>
                              <option value="Indígena"             {{ old('raca_cor') == 'Indígena'             ? 'selected' : '' }}>Indígena</option>
                              <option value="Prefere não declarar" {{ old('raca_cor') == 'Prefere não declarar' ? 'selected' : '' }}>Prefere não declarar</option>
                          </select>
                          @error('raca_cor')
                          <div class="invalid-feedback">{{ $message }}</div>
                          @enderror
                      </div>

                      {{-- 3. Comunidade Tradicional --}}
                      <div class="mb-3">
                          <label for="comunidade_tradicional_modal" class="form-label fw-semibold">
                              Pertencimento a Povos ou Comunidades Tradicionais <span class="text-danger">*</span>
                          </label>
                          <select name="comunidade_tradicional" id="comunidade_tradicional_modal"
                                  class="form-select @error('comunidade_tradicional') is-invalid @enderror"
                                  required onchange="toggleOutro(this, 'comunidade_tradicional_outro_wrap_modal')">
                              <option value="" disabled selected>Selecione...</option>
                              <option value="Não"                    {{ old('comunidade_tradicional') == 'Não'                    ? 'selected' : '' }}>Não</option>
                              <option value="Povos indígenas"        {{ old('comunidade_tradicional') == 'Povos indígenas'        ? 'selected' : '' }}>Povos indígenas</option>
                              <option value="Comunidades Quilombolas" {{ old('comunidade_tradicional') == 'Comunidades Quilombolas' ? 'selected' : '' }}>Comunidades Quilombolas</option>
                              <option value="Povos Ciganos"          {{ old('comunidade_tradicional') == 'Povos Ciganos'          ? 'selected' : '' }}>Povos Ciganos</option>
                              <option value="Ribeirinhos"            {{ old('comunidade_tradicional') == 'Ribeirinhos'            ? 'selected' : '' }}>Ribeirinhos</option>
                              <option value="Extrativistas"          {{ old('comunidade_tradicional') == 'Extrativistas'          ? 'selected' : '' }}>Extrativistas</option>
                              <option value="Outro"                  {{ old('comunidade_tradicional') == 'Outro'                  ? 'selected' : '' }}>Outro</option>
                          </select>
                          @error('comunidade_tradicional')
                          <div class="invalid-feedback">{{ $message }}</div>
                          @enderror
                          <div id="comunidade_tradicional_outro_wrap_modal" class="mt-2" style="display:none">
                              <input type="text" name="comunidade_tradicional_outro"
                                     class="form-control @error('comunidade_tradicional_outro') is-invalid @enderror"
                                     placeholder="Especifique a comunidade tradicional"
                                     value="{{ old('comunidade_tradicional_outro') }}">
                              @error('comunidade_tradicional_outro')
                              <div class="invalid-feedback">{{ $message }}</div>
                              @enderror
                          </div>
                      </div>

                      {{-- 4. Faixa Etária --}}
                      <div class="mb-3">
                          <label for="faixa_etaria_modal" class="form-label fw-semibold">
                              Faixa Etária <span class="text-danger">*</span>
                          </label>
                          <select name="faixa_etaria" id="faixa_etaria_modal"
                                  class="form-select @error('faixa_etaria') is-invalid @enderror"
                                  required>
                              <option value="" disabled selected>Selecione...</option>
                              <option value="Primeira infância (0 a 6 anos)"  {{ old('faixa_etaria') == 'Primeira infância (0 a 6 anos)'  ? 'selected' : '' }}>Primeira infância (0 a 6 anos)</option>
                              <option value="Criança (7 a 11 anos)"           {{ old('faixa_etaria') == 'Criança (7 a 11 anos)'           ? 'selected' : '' }}>Criança (7 a 11 anos)</option>
                              <option value="Adolescente (12 a 17 anos)"      {{ old('faixa_etaria') == 'Adolescente (12 a 17 anos)'      ? 'selected' : '' }}>Adolescente (12 a 17 anos)</option>
                              <option value="Adulto (18 a 59 anos)"           {{ old('faixa_etaria') == 'Adulto (18 a 59 anos)'           ? 'selected' : '' }}>Adulto (18 a 59 anos)</option>
                              <option value="Idoso (a partir dos 60 anos)"    {{ old('faixa_etaria') == 'Idoso (a partir dos 60 anos)'    ? 'selected' : '' }}>Idoso (a partir dos 60 anos)</option>
                          </select>
                          @error('faixa_etaria')
                          <div class="invalid-feedback">{{ $message }}</div>
                          @enderror
                      </div>

                      {{-- 5. PcD --}}
                      <div class="mb-3">
                          <label for="pcd_modal" class="form-label fw-semibold">
                              Pessoa com Deficiência (PcD) <span class="text-danger">*</span>
                          </label>
                          <select name="pcd" id="pcd_modal"
                                  class="form-select @error('pcd') is-invalid @enderror"
                                  required>
                              <option value="" disabled selected>Selecione...</option>
                              <option value="Não"         {{ old('pcd') == 'Não'         ? 'selected' : '' }}>Não</option>
                              <option value="Física"      {{ old('pcd') == 'Física'      ? 'selected' : '' }}>Física</option>
                              <option value="Auditiva"    {{ old('pcd') == 'Auditiva'    ? 'selected' : '' }}>Auditiva</option>
                              <option value="Visual"      {{ old('pcd') == 'Visual'      ? 'selected' : '' }}>Visual</option>
                              <option value="Intelectual" {{ old('pcd') == 'Intelectual' ? 'selected' : '' }}>Intelectual</option>
                              <option value="Múltipla"    {{ old('pcd') == 'Múltipla'    ? 'selected' : '' }}>Múltipla</option>
                          </select>
                          @error('pcd')
                          <div class="invalid-feedback">{{ $message }}</div>
                          @enderror
                      </div>

                      {{-- 6. Orientação Sexual --}}
                      <div class="mb-3">
                          <label for="orientacao_sexual_modal" class="form-label fw-semibold">
                              Orientação Sexual <span class="text-danger">*</span>
                          </label>
                          <select name="orientacao_sexual" id="orientacao_sexual_modal"
                                  class="form-select @error('orientacao_sexual') is-invalid @enderror"
                                  required onchange="toggleOutro(this, 'orientacao_sexual_outra_wrap_modal')">
                              <option value="" disabled selected>Selecione...</option>
                              <option value="Lésbica"              {{ old('orientacao_sexual') == 'Lésbica'              ? 'selected' : '' }}>Lésbica</option>
                              <option value="Gay"                  {{ old('orientacao_sexual') == 'Gay'                  ? 'selected' : '' }}>Gay</option>
                              <option value="Bissexual"            {{ old('orientacao_sexual') == 'Bissexual'            ? 'selected' : '' }}>Bissexual</option>
                              <option value="Heterossexual"        {{ old('orientacao_sexual') == 'Heterossexual'        ? 'selected' : '' }}>Heterossexual</option>
                              <option value="Prefere não declarar" {{ old('orientacao_sexual') == 'Prefere não declarar' ? 'selected' : '' }}>Prefere não declarar</option>
                              <option value="Outra"                {{ old('orientacao_sexual') == 'Outra'                ? 'selected' : '' }}>Outra</option>
                          </select>
                          @error('orientacao_sexual')
                          <div class="invalid-feedback">{{ $message }}</div>
                          @enderror
                          <div id="orientacao_sexual_outra_wrap_modal" class="mt-2" style="display:none">
                              <input type="text" name="orientacao_sexual_outra"
                                     class="form-control @error('orientacao_sexual_outra') is-invalid @enderror"
                                     placeholder="Especifique sua orientação sexual"
                                     value="{{ old('orientacao_sexual_outra') }}">
                              @error('orientacao_sexual_outra')
                              <div class="invalid-feedback">{{ $message }}</div>
                              @enderror
                          </div>
                      </div>

                  </form>
              </div>

              <div class="modal-footer">
                  <button type="submit" form="form-demograficos" class="btn btn-engaja w-100">
                      Salvar e continuar
                  </button>
              </div>

          </div>
      </div>
  </div>

  <script>
      document.addEventListener('DOMContentLoaded', function () {
          const modalEl = document.getElementById('modalCompletarPerfil');
          if (modalEl) {
              const modal = new bootstrap.Modal(modalEl);
              modal.show();

              restoreOutroFields();
          }
      });

      function toggleOutro(select, wrapId) {
          const wrap = document.getElementById(wrapId);
          if (!wrap) return;
          const mostrar = select.value === 'Outro' || select.value === 'Outra';
          wrap.style.display = mostrar ? 'block' : 'none';
          const input = wrap.querySelector('input');
          if (input) input.required = mostrar;
      }

      function restoreOutroFields() {
          [
              { selectId: 'identidade_genero_modal',      wrapId: 'identidade_genero_outro_wrap_modal' },
              { selectId: 'comunidade_tradicional_modal', wrapId: 'comunidade_tradicional_outro_wrap_modal' },
              { selectId: 'orientacao_sexual_modal',      wrapId: 'orientacao_sexual_outra_wrap_modal' },
          ].forEach(({ selectId, wrapId }) => {
              const select = document.getElementById(selectId);
              if (select && (select.value === 'Outro' || select.value === 'Outra')) {
                  const wrap = document.getElementById(wrapId);
                  if (wrap) wrap.style.display = 'block';
              }
          });
      }
  </script>
  @endif

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      
      document.addEventListener('change', function (e) {
          const cb = e.target;
          if (!cb.classList.contains('js-checklist-item')) return;

          const modalId = cb.dataset.modal;
          const total   = parseInt(cb.dataset.total, 10);
          const checked = document.querySelectorAll(
              `#${modalId} .js-checklist-item:checked`
          ).length;

          const card = cb.closest('.checklist-card');
          if (card) card.classList.toggle('checked', cb.checked);

          // ── Contador ────────────────────────────────────
          const counter = document.querySelector(`.js-counter[data-modal="${modalId}"]`);
          if (counter) counter.textContent = `${checked} / ${total}`;

          // ── Barra de progresso ──────────────────────────
          const bar = document.querySelector(`.js-progress[data-modal="${modalId}"]`);
          if (bar) {
              const pct = Math.round((checked / total) * 100);
              bar.style.width = pct + '%';
              bar.setAttribute('aria-valuenow', pct);
          }

          // ── Botão confirmar ─────────
          const btn = document.querySelector(`.js-checklist-confirm[data-modal="${modalId}"]`);
          if (btn) btn.disabled = false; 
      });

      // ── Reset ao fechar ─────────────────────────────────
      document.querySelectorAll('.modal').forEach(function (modalEl) {
          modalEl.addEventListener('hidden.bs.modal', function () {
              const id    = modalEl.id;
              
              if (id === 'modalCompletarPerfil') return;

              const btn = modalEl.querySelector('.js-checklist-confirm');
              if (btn) btn.disabled = false; 
          });
      });

  });
  </script>

</body>
</html>