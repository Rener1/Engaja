<aside class="admin-sidebar" id="adminSidebar">
  <div class="admin-sidebar__brand">
    <a href="{{ url('/') }}" class="d-flex align-items-center gap-3 text-decoration-none text-white">
      <img src="{{ asset('images/engaja-bg-white.png') }}" alt="Logo Engaja" class="admin-sidebar__logo admin-sidebar__logo-main">
      <img src="{{ asset('images/engaja-favicon.png') }}" alt="Logo mini" class="admin-sidebar__logo admin-sidebar__logo-mini">
    </a>
    <div class="admin-sidebar__actions">
      <button type="button" class="admin-collapse-btn d-none d-lg-inline-flex" id="sidebarCollapseToggle" aria-label="Recolher menu lateral">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
          <path fill-rule="evenodd" d="M12.5 2a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 1 0v-11a.5.5 0 0 0-.5-.5M6.646 4.146a.5.5 0 0 1 .708.708L4.707 7.5H7.5a.5.5 0 0 1 0 1H4.707l2.647 2.646a.5.5 0 0 1-.708.708l-3.5-3.5a.5.5 0 0 1 0-.708z"/>
        </svg>
      </button>
      <button type="button" class="btn btn-sm btn-outline-light d-lg-none" id="sidebarClose">Fechar</button>
    </div>
  </div>

  <div class="admin-sidebar__section">
    <p class="admin-sidebar__label">Navegação</p>
    <a class="admin-nav-link {{ request()->is('/') ? 'active' : '' }}" href="{{ url('/') }}">
      <span class="admin-nav-icon" aria-hidden="true">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
          <path d="M7.293 1.5a1 1 0 0 1 1.414 0l5.793 5.793a1 1 0 0 1 .293.707V14a1 1 0 0 1-1 1h-4a.5.5 0 0 1-.5-.5V11a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v3.5a.5.5 0 0 1-.5.5h-4a1 1 0 0 1-1-1V8c0-.266.105-.52.293-.707z"/>
        </svg>
      </span>
      <span class="admin-nav-text">Início</span>
    </a>
  </div>

  <div class="admin-sidebar__section">
    <p class="admin-sidebar__label">Módulos</p>
    <div class="accordion accordion-flush admin-sidebar__accordion" id="sidebarAccordion">
      @hasanyrole('administrador|gerente|eq_pedagogica|articulador')
        @php($overviewOpen = request()->routeIs('dashboard') || request()->routeIs('dashboards.*'))
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingOverview">
            <button class="accordion-button admin-accordion-button {{ $overviewOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarOverview" aria-expanded="{{ $overviewOpen ? 'true' : 'false' }}" aria-controls="sidebarOverview">
              <span class="admin-nav-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M3 2h3v5H3zM10 2h3v3h-3zM10 7h3v7h-3zM3 9h3v5H3z"/>
                </svg>
              </span>
              <span class="admin-nav-text">Visão geral</span>
            </button>
          </h2>
          <div id="sidebarOverview" class="accordion-collapse collapse {{ $overviewOpen ? 'show' : '' }}" aria-labelledby="headingOverview" data-bs-parent="#sidebarAccordion">
            <div class="accordion-body">
              <a class="admin-nav-link {{ request()->routeIs('dashboard') || request()->routeIs('dashboards.*') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                Dashboards
              </a>
            </div>
          </div>
        </div>
      @endhasanyrole

      @hasanyrole('administrador|gerente|eq_pedagogica|articulador|SME')
        @php($operacoesOpen = request()->routeIs('eventos.*') || request()->routeIs('agendamentos.*') || request()->routeIs('atividade-acoes.*') || request()->routeIs('presencas.*'))
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingOperacoes">
            <button class="accordion-button admin-accordion-button {{ $operacoesOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarOperacoes" aria-expanded="{{ $operacoesOpen ? 'true' : 'false' }}" aria-controls="sidebarOperacoes">
              <span class="admin-nav-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M2 2h12v2H2zM2 6h9v2H2zM2 10h6v2H2z"/>
                </svg>
              </span>
              <span class="admin-nav-text">Operações</span>
            </button>
          </h2>
          <div id="sidebarOperacoes" class="accordion-collapse collapse {{ $operacoesOpen ? 'show' : '' }}" aria-labelledby="headingOperacoes" data-bs-parent="#sidebarAccordion">
            <div class="accordion-body">
              @hasanyrole('administrador|gerente|eq_pedagogica|articulador')
                <a class="admin-nav-link {{ request()->routeIs('eventos.*') ? 'active' : '' }}" href="{{ route('eventos.index') }}">
                  Ações pedagógicas
                </a>
              @endhasanyrole
              @can('presenca.abrir')
                <a class="admin-nav-link {{ request()->routeIs('presencas.*') ? 'active' : '' }}" href="{{ route('presencas.gerenciamento') }}">
                  Controle de presenças
                </a>
              @endcan
              <a class="admin-nav-link {{ request()->routeIs('agendamentos.*') && !request()->routeIs('agendamentos.efetivacoes.*') ? 'active' : '' }}" href="{{ route('agendamentos.index') }}">
                Agendamentos
              </a>
              @hasanyrole('administrador|gerente|eq_pedagogica')
                <a class="admin-nav-link {{ request()->routeIs('agendamentos.efetivacoes.*') ? 'active' : '' }}" href="{{ route('agendamentos.efetivacoes.index') }}">
                  Efetivações
                </a>
                <a class="admin-nav-link {{ request()->routeIs('atividade-acoes.*') ? 'active' : '' }}" href="{{ route('atividade-acoes.index') }}">
                  Atividade/Ação
                </a>
              @endhasanyrole
            </div>
          </div>
        </div>
      @endhasanyrole

      @hasanyrole('administrador|gerente|eq_pedagogica|articulador')
        @php($avaliacoesOpen = request()->routeIs('avaliacoes.*') || request()->routeIs('avaliacoes-universais.*') || request()->routeIs('avaliacoes-consolidadas.*') || request()->routeIs('templates-avaliacao.*') || request()->routeIs('dimensaos.*') || request()->routeIs('indicadors.*') || request()->routeIs('evidencias.*') || request()->routeIs('escalas.*'))
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingAvaliacoes">
            <button class="accordion-button admin-accordion-button {{ $avaliacoesOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarAvaliacoes" aria-expanded="{{ $avaliacoesOpen ? 'true' : 'false' }}" aria-controls="sidebarAvaliacoes">
              <span class="admin-nav-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M3 3a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V6.414a1 1 0 0 0-.293-.707l-3.414-3.414A1 1 0 0 0 9.586 2H3zm6-1.5v3a.5.5 0 0 0 .5.5h3z"/>
                </svg>
              </span>
              <span class="admin-nav-text">Avaliações</span>
            </button>
          </h2>
          <div id="sidebarAvaliacoes" class="accordion-collapse collapse {{ $avaliacoesOpen ? 'show' : '' }}" aria-labelledby="headingAvaliacoes" data-bs-parent="#sidebarAccordion">
            <div class="accordion-body">
              <a class="admin-nav-link {{ request()->routeIs('avaliacoes.*') ? 'active' : '' }}" href="{{ route('avaliacoes.index') }}">
                Aplicar avaliações
              </a>
              <a class="admin-nav-link {{ request()->routeIs('avaliacoes-universais.*') ? 'active' : '' }}" href="{{ route('avaliacoes-universais.index') }}">
                Avaliações universais
              </a>
              @hasanyrole('administrador|gerente|eq_pedagogica')
              <a class="admin-nav-link {{ request()->routeIs('avaliacoes-consolidadas.*') ? 'active' : '' }}" href="{{ route('avaliacoes-consolidadas.index') }}">
                Consolidação de avaliações
              </a>
              @endhasanyrole
              <div class="admin-subsection__label">Configurações</div>
              <a class="admin-nav-link {{ request()->routeIs('templates-avaliacao.*') ? 'active' : '' }}" href="{{ route('templates-avaliacao.index') }}">
                Modelos de avaliação
              </a>
              <a class="admin-nav-link {{ request()->routeIs('dimensaos.*') ? 'active' : '' }}" href="{{ route('dimensaos.index') }}">
                Dimensões
              </a>
              <a class="admin-nav-link {{ request()->routeIs('indicadors.*') ? 'active' : '' }}" href="{{ route('indicadors.index') }}">
                Indicadores
              </a>
              <a class="admin-nav-link {{ request()->routeIs('evidencias.*') ? 'active' : '' }}" href="{{ route('evidencias.index') }}">
                Evidências
              </a>
              <a class="admin-nav-link {{ request()->routeIs('escalas.*') ? 'active' : '' }}" href="{{ route('escalas.index') }}">
                Escalas
              </a>
            </div>
          </div>
        </div>

        @php($relatoriosOpen = request()->routeIs('avaliacao-atividade.*') || request()->routeIs('relatorio-quantitativo.*') || request()->routeIs('painel-gerencial.*'))
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingRelatorios">
            <button class="accordion-button admin-accordion-button {{ $relatoriosOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarRelatorios" aria-expanded="{{ $relatoriosOpen ? 'true' : 'false' }}" aria-controls="sidebarRelatorios">
              <span class="admin-nav-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M0 0h1v15h15v1H0zm10 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0V4.9l-3.613 4.417a.5.5 0 0 1-.74.037L7.06 6.767l-3.656 5.027a.5.5 0 0 1-.808-.588l4-5.5a.5.5 0 0 1 .758-.06l2.609 2.61L13.445 4H10.5a.5.5 0 0 1-.5-.5"/>
                </svg>
              </span>
              <span class="admin-nav-text">Relatórios</span>
            </button>
          </h2>
          <div id="sidebarRelatorios" class="accordion-collapse collapse {{ $relatoriosOpen ? 'show' : '' }}" aria-labelledby="headingRelatorios" data-bs-parent="#sidebarAccordion">
            <div class="accordion-body">
              <a class="admin-nav-link {{ request()->routeIs('avaliacao-atividade.*') ? 'active' : '' }}" href="{{ route('avaliacao-atividade.index') }}">
                Relatórios do Momento
              </a>
              <a class="admin-nav-link {{ request()->routeIs('relatorio-quantitativo.*') ? 'active' : '' }}" href="{{ route('relatorio-quantitativo.index') }}">
                Relatório Quantitativo
              </a>

              <a class="admin-nav-link {{ request()->routeIs('painel-gerencial.*') ? 'active' : '' }}" href="{{ route('painel-gerencial.index') }}">
                Painel Gerencial
              </a>
            </div>
          </div>
        </div>

        @php($pessoasOpen = request()->routeIs('usuarios.*'))
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingPessoas">
            <button class="accordion-button admin-accordion-button {{ $pessoasOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarPessoas" aria-expanded="{{ $pessoasOpen ? 'true' : 'false' }}" aria-controls="sidebarPessoas">
              <span class="admin-nav-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M8 9a3 3 0 1 0-3-3 3 3 0 0 0 3 3m4.5 5a.5.5 0 0 0 .5-.5c0-1.657-2.239-3-5-3s-5 1.343-5 3a.5.5 0 0 0 .5.5z"/>
                </svg>
              </span>
              <span class="admin-nav-text">Pessoas</span>
            </button>
          </h2>
          <div id="sidebarPessoas" class="accordion-collapse collapse {{ $pessoasOpen ? 'show' : '' }}" aria-labelledby="headingPessoas" data-bs-parent="#sidebarAccordion">
            <div class="accordion-body">
              @role('administrador')
                <a class="admin-nav-link {{ request()->routeIs('usuarios.atualizacao-cadastral.*') ? 'active' : '' }}" href="{{ route('usuarios.atualizacao-cadastral.index') }}">
                  Atualização de dados cadastrais
                </a>
              @endrole
              <a class="admin-nav-link {{ request()->routeIs('usuarios.index') || request()->routeIs('usuarios.edit') || request()->routeIs('usuarios.update') ? 'active' : '' }}" href="{{ route('usuarios.index') }}">
                Gerenciar usuários
              </a>
              @hasanyrole('administrador|gerente')
                <a class="admin-nav-link {{ request()->routeIs('usuarios.participantes-exclusivos.*') ? 'active' : '' }}" href="{{ route('usuarios.participantes-exclusivos.index') }}">
                  Participantes exclusivos
                </a>
                <a class="admin-nav-link {{ request()->routeIs('usuarios.sem-vinculo.*') ? 'active' : '' }}" href="{{ route('usuarios.sem-vinculo.index') }}">
                  Usuários sem vínculo
                </a>


              @endhasanyrole
              <a class="admin-nav-link {{ request()->routeIs('usuarios.verificar.*') ? 'active' : '' }}" href="{{ route('usuarios.verificar.index') }}">
                Verificar usuário
              </a>
            </div>
          </div>
        </div>
      @endhasanyrole

      @hasanyrole('administrador|gerente|eq_pedagogica|articulador|participante|SME')
        @php($certificadosOpen = request()->routeIs('profile.certificados') || request()->routeIs('certificados.*'))
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingCertificados">
            <button class="accordion-button admin-accordion-button {{ $certificadosOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCertificados" aria-expanded="{{ $certificadosOpen ? 'true' : 'false' }}" aria-controls="sidebarCertificados">
              <span class="admin-nav-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M2 1.5A1.5 1.5 0 0 1 3.5 0h6A1.5 1.5 0 0 1 11 1.5V4h1.5A1.5 1.5 0 0 1 14 5.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z"/>
                </svg>
              </span>
              <span class="admin-nav-text">Certificados</span>
            </button>
          </h2>
          <div id="sidebarCertificados" class="accordion-collapse collapse {{ $certificadosOpen ? 'show' : '' }}" aria-labelledby="headingCertificados" data-bs-parent="#sidebarAccordion">
            <div class="accordion-body">
              <a class="admin-nav-link {{ request()->routeIs('profile.certificados') ? 'active' : '' }}" href="{{ route('profile.certificados') }}">
                Meus certificados
              </a>
              @hasanyrole('administrador|gerente')
                <a class="admin-nav-link {{ request()->routeIs('certificados.modelos.*') ? 'active' : '' }}" href="{{ route('certificados.modelos.index') }}">
                  Modelos de certificados
                </a>
              @endhasanyrole
              @can('certificado.baixar')
                <a class="admin-nav-link {{ request()->routeIs('certificados.emitidos') ? 'active' : '' }}" href="{{ route('certificados.emitidos') }}">
                  Certificados emitidos
                </a>
              @endcan
            </div>
          </div>
        </div>
      @endhasanyrole

      @role('administrador')
        @php($administracaoOpen = request()->routeIs('regioes.*') || request()->routeIs('estados.*') || request()->routeIs('municipios.*'))
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingAdministracao">
            <button class="accordion-button admin-accordion-button {{ $administracaoOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarAdministracao" aria-expanded="{{ $administracaoOpen ? 'true' : 'false' }}" aria-controls="sidebarAdministracao">
              <span class="admin-nav-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                  <path d="M2 3a1 1 0 0 1 1-1h4v4H2zm0 5h5v6H3a1 1 0 0 1-1-1zm6 6V8h6v5a1 1 0 0 1-1 1zm6-7H8V2h5a1 1 0 0 1 1 1z"/>
                </svg>
              </span>
              <span class="admin-nav-text">Administração</span>
            </button>
          </h2>
          <div id="sidebarAdministracao" class="accordion-collapse collapse {{ $administracaoOpen ? 'show' : '' }}" aria-labelledby="headingAdministracao" data-bs-parent="#sidebarAccordion">
            <div class="accordion-body">
              <a class="admin-nav-link {{ request()->routeIs('regioes.*') ? 'active' : '' }}" href="{{ route('regioes.index') }}">
                Regiões
              </a>
              <a class="admin-nav-link {{ request()->routeIs('estados.*') ? 'active' : '' }}" href="{{ route('estados.index') }}">
                Estados
              </a>
              <a class="admin-nav-link {{ request()->routeIs('municipios.*') ? 'active' : '' }}" href="{{ route('municipios.index') }}">
                Municípios
              </a>
            </div>
          </div>
        </div>
      @endrole
    </div>
  </div>

  <div class="admin-sidebar__section admin-sidebar__account mt-auto">
    <p class="admin-sidebar__label">Minha conta</p>
    <a class="admin-nav-link {{ request()->routeIs('profile.edit') ? 'active' : '' }}" href="{{ route('profile.edit') }}">
      @if (auth()->user()?->profile_photo_url)
        <img src="{{ auth()->user()->profile_photo_url }}"
             alt="Foto de perfil de {{ auth()->user()->name }}"
             class="admin-nav-icon rounded-circle border border-white border-opacity-25"
             style="object-fit: cover;">
      @else
        <span class="admin-nav-icon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 8a3 3 0 1 0-3-3 3 3 0 0 0 3 3m4 5.5a5 5 0 1 0-8 0z"/>
          </svg>
        </span>
      @endif
      <span class="admin-nav-text">Meu perfil</span>
    </a>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="admin-nav-link btn btn-link text-start w-100">
        <span class="admin-nav-icon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
            <path d="M6.146 11.854a.5.5 0 0 0 .708 0L10.207 8.5 6.854 5.146a.5.5 0 1 0-.708.708L8.793 8.5z"/>
            <path d="M3.5 15A1.5 1.5 0 0 1 2 13.5v-11A1.5 1.5 0 0 1 3.5 1h5A1.5 1.5 0 0 1 10 2.5V5h-1V2.5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 .5.5h5a.5.5 0 0 0 .5-.5V10h1v3.5a1.5 1.5 0 0 1-1.5 1.5z"/>
          </svg>
        </span>
        <span class="admin-nav-text">Sair</span>
      </button>
    </form>
  </div>
</aside>
