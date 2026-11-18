@extends('layouts.app')

@section('content')
<x-ui.page-hero contained eyebrow="Dashboards" eyebrow-icon="grid" title="Escolha o painel" class="mb-0">
  <x-slot:actions>
    <a class="eg-hero-btn" href="{{ route('dashboards.bi') }}"><i class="bi bi-bar-chart-line me-1"></i> BI Educacional</a>
    <a class="eg-hero-btn" href="{{ route('dashboards.avaliacoes') }}"><i class="bi bi-chat-square-text me-1"></i> Respostas de formulários</a>
    <a class="eg-hero-btn" href="{{ route('dashboards.presencas') }}"><i class="bi bi-people me-1"></i> Presenças e inscrições</a>
    <a class="eg-hero-btn" href="{{ route('dashboards.leitura-mundo') }}"><i class="bi bi-globe2 me-1"></i> Leitura do mundo</a>
  </x-slot:actions>
</x-ui.page-hero>

<div class="container py-4">
  <div class="row g-3 mb-4">
    <div class="col-lg-3">
      <div class="card h-100 shadow-sm border-0">
        <div class="card-body d-flex">
          <div class="d-flex flex-column w-100">
              <p class="text-uppercase text-primary small fw-semibold mb-1">Respostas</p>
              <h2 class="h5 fw-bold">Painel de formulários</h2>
              <p class="text-muted mb-3">
                Acompanhe médias, distribuições e respostas abertas com gráficos dinâmicos.
                Filtre por modelo, ação pedagógica ou período.
              </p>
              <a href="{{ route('dashboards.avaliacoes') }}" class="btn btn-primary mt-auto align-self-start">
                Abrir dashboard de respostas
              </a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-3">
      <div class="card h-100 shadow-sm border-0">
        <div class="card-body d-flex">
          <div class="d-flex flex-column w-100">
              <p class="text-uppercase text-success small fw-semibold mb-1">Presença</p>
              <h2 class="h5 fw-bold">Painel de presenças e inscrições</h2>
              <p class="text-muted mb-3">
                Acompanhe presenças e inscritos por ação pedagógica,
                com filtros, expansão de linhas e exportação em PDF.
              </p>
              <a href="{{ route('dashboards.presencas') }}" class="btn btn-success mt-auto align-self-start">
                Abrir dashboard de presenças
              </a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-3">
      <div class="card h-100 shadow-sm border-0">
        <div class="card-body d-flex">
          <div class="d-flex flex-column w-100">
              <p class="text-uppercase small fw-semibold mb-1" style="color:#421944;">BI</p>
              <h2 class="h5 fw-bold">Painel de indicadores educacionais</h2>
              <p class="text-muted mb-3">
                Acompanhe rankings e recortes por município para apoiar análises e decisões.
              </p>
              <a href="{{ route('dashboards.bi') }}" class="btn btn-secondary mt-auto align-self-start">
                Abrir dashboard BI
              </a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-3">
      <div class="card h-100 shadow-sm border-0">
        <div class="card-body d-flex">
          <div class="d-flex flex-column w-100">
              <p class="text-uppercase small fw-semibold mb-1" style="color:#6c345e;">Leitura</p>
              <h2 class="h5 fw-bold">Painel Leitura do mundo</h2>
              <p class="text-muted mb-3">
                Liste as leituras cadastradas no LimeSurvey e abra o dashboard por survey.
              </p>
              <a href="{{ route('dashboards.leitura-mundo') }}" class="btn btn-dark mt-auto align-self-start">
                Abrir leitura do mundo
              </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-0">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <p class="text-uppercase small text-muted mb-0">Modelos de formulário</p>
              <h3 class="h6 fw-bold mb-0">Últimos utilizados</h3>
            </div>
          </div>
        </div>
        <div class="list-group list-group-flush">
          @forelse($templatesDisponiveis as $template)
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold">{{ $template->nome }}</div>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('dashboards.avaliacoes', ['template_id' => $template->id]) }}">
              Ver respostas
            </a>
          </div>
          @empty
          <div class="list-group-item text-muted">Nenhum modelo cadastrado ainda.</div>
          @endforelse
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-header bg-white border-0">
          <p class="text-uppercase small text-muted mb-0">Ações pedagógicas recentes</p>
          <h3 class="h6 fw-bold mb-0">O que recebeu inscrições</h3>
        </div>
        <div class="card-body">
          <div class="vstack gap-3">
            @forelse($eventosRecentes as $evento)
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold">{{ $evento->nome }}</div>
                <small class="text-muted">Criado em {{ optional($evento->created_at)->format('d/m/Y') }}</small>
              </div>
              <a href="{{ route('eventos.show', $evento) }}" class="btn btn-sm btn-outline-secondary">Ver ação</a>
            </div>
            @empty
            <div class="text-muted">Nenhuma ação pedagógica registrada.</div>
            @endforelse
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
