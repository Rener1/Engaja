@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Dashboards" eyebrow-icon="grid" title="Escolha o painel" class="mb-0">
  <x-slot:actions>
    <a class="eg-hero-btn" href="{{ route('dashboards.bi') }}"><i class="bi bi-bar-chart-line me-1"></i> BI Educacional</a>
    <a class="eg-hero-btn" href="{{ route('dashboards.avaliacoes') }}"><i class="bi bi-chat-square-text me-1"></i> Respostas de formulários</a>
    <a class="eg-hero-btn" href="{{ route('dashboards.presencas') }}"><i class="bi bi-people me-1"></i> Presenças e inscrições</a>
    <a class="eg-hero-btn" href="{{ route('dashboards.leitura-mundo') }}"><i class="bi bi-globe2 me-1"></i> Leitura do mundo</a>
  </x-slot:actions>
</x-ui.page-hero>

@php
  $paineis = [
    ['cor' => 'purple', 'icone' => 'chat-square-text', 'rotulo' => 'Respostas', 'titulo' => 'Painel de formulários',
     'texto' => 'Acompanhe médias, distribuições e respostas abertas com gráficos dinâmicos. Filtre por modelo, ação pedagógica ou período.',
     'rota' => route('dashboards.avaliacoes'), 'botao' => 'Abrir dashboard de respostas'],
    ['cor' => 'green', 'icone' => 'people', 'rotulo' => 'Presença', 'titulo' => 'Painel de presenças e inscrições',
     'texto' => 'Acompanhe presenças e inscritos por ação pedagógica, com filtros, expansão de linhas e exportação em PDF.',
     'rota' => route('dashboards.presencas'), 'botao' => 'Abrir dashboard de presenças'],
    ['cor' => 'blue', 'icone' => 'bar-chart-line', 'rotulo' => 'BI', 'titulo' => 'Painel de indicadores educacionais',
     'texto' => 'Acompanhe rankings e recortes por município para apoiar análises e decisões.',
     'rota' => route('dashboards.bi'), 'botao' => 'Abrir dashboard BI'],
    ['cor' => 'orange', 'icone' => 'globe2', 'rotulo' => 'Leitura', 'titulo' => 'Painel Leitura do mundo',
     'texto' => 'Liste as leituras cadastradas no LimeSurvey e abra o dashboard por survey.',
     'rota' => route('dashboards.leitura-mundo'), 'botao' => 'Abrir leitura do mundo'],
  ];
@endphp

<div class="py-4">
  <div class="row g-4 mb-4">
    @foreach($paineis as $painel)
      <div class="col-md-6 col-xl-3">
        <a href="{{ $painel['rota'] }}" class="eg-nav-card eg-stat-card--{{ $painel['cor'] }} h-100">
          <span class="eg-stat-icon"><i class="bi bi-{{ $painel['icone'] }}"></i></span>
          <span class="eg-nav-card__eyebrow">{{ $painel['rotulo'] }}</span>
          <span class="eg-nav-card__title">{{ $painel['titulo'] }}</span>
          <span class="eg-nav-card__text">{{ $painel['texto'] }}</span>
          <span class="eg-nav-card__cta">{{ $painel['botao'] }} <i class="bi bi-arrow-right"></i></span>
        </a>
      </div>
    @endforeach
  </div>

  <div class="row g-4">
    <div class="col-lg-7">
      <section class="eg-section-card h-100">
        <p class="text-uppercase small text-muted mb-1">Modelos de formulário</p>
        <h3 class="eg-section-card__title"><i class="bi bi-ui-checks"></i> Últimos utilizados</h3>
        <div class="eg-list">
          @forelse($templatesDisponiveis as $template)
            <div class="eg-list__item">
              <div class="eg-list__main">
                <div class="eg-list__title">{{ $template->nome }}</div>
              </div>
              <a class="btn btn-sm btn-outline-primary" href="{{ route('dashboards.avaliacoes', ['template_id' => $template->id]) }}">
                Ver respostas
              </a>
            </div>
          @empty
            <x-ui.empty-state compact muted icon="ui-checks">Nenhum modelo cadastrado ainda.</x-ui.empty-state>
          @endforelse
        </div>
      </section>
    </div>
    <div class="col-lg-5">
      <section class="eg-section-card h-100">
        <p class="text-uppercase small text-muted mb-1">Ações pedagógicas recentes</p>
        <h3 class="eg-section-card__title"><i class="bi bi-calendar-event"></i> O que recebeu inscrições</h3>
        <div class="eg-list">
          @forelse($eventosRecentes as $evento)
            <div class="eg-list__item">
              <div class="eg-list__main">
                <div class="eg-list__title">{{ $evento->nome }}</div>
                <div class="eg-list__meta">Criado em {{ optional($evento->created_at)->format('d/m/Y') }}</div>
              </div>
              <a href="{{ route('eventos.show', $evento) }}" class="btn btn-sm btn-outline-primary">Ver ação</a>
            </div>
          @empty
            <x-ui.empty-state compact muted icon="calendar-x">Nenhuma ação pedagógica registrada.</x-ui.empty-state>
          @endforelse
        </div>
      </section>
    </div>
  </div>
</div>
@endsection
