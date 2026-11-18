@extends('layouts.app')

@section('content')
<x-ui.page-hero
    eyebrow="Dashboards" eyebrow-icon="chat-square-text"
    title="Respostas dos formulários"
    class="mb-0">
    <x-slot:actions>
        <button type="button" id="btn-exportar-pdf" class="eg-hero-btn">
            <i class="bi bi-file-earmark-pdf-fill me-1"></i> Gerar PDF
        </button>
        <a href="{{ route('dashboard') }}" class="eg-hero-btn"><i class="bi bi-grid me-1"></i> Hub de dashboards</a>
        <a href="{{ route('dashboards.bi') }}" class="eg-hero-btn"><i class="bi bi-bar-chart-line me-1"></i> Ir para BI</a>
        <a href="{{ route('dashboards.presencas') }}" class="eg-hero-btn"><i class="bi bi-people me-1"></i> Ir para presenças</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="py-4" id="avaliacoes-dashboard" data-endpoint="{{ route('dashboards.avaliacoes.data', request()->only(['fonte', 'survey_id'])) }}">

  @if(request('fonte') === 'limesurvey')
    <div class="alert alert-info border-0 shadow-sm py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span>
        Fonte ativa: <strong>LimeSurvey</strong>
        @if(request('survey_id'))
          (survey_id={{ request('survey_id') }})
        @endif
      </span>
      @if(!empty($cachedAt))
        <span class="text-muted small">
          Dados atualizados em: <strong>{{ $cachedAt }}</strong>
        </span>
      @else
        <span class="text-muted small">Dados ao vivo (sem cache)</span>
      @endif
    </div>
  @endif

  <x-ui.filter-card>
      <div class="mb-3">
        <span class="eg-filter-label"><i class="bi bi-ui-radios me-1"></i> Tipo de formulário</span>
        <div class="eg-segmented eg-segmented--auto" role="group" aria-label="Tipo de formulário">
          <input type="radio" class="btn-check js-filter" name="tipo-dashboard" id="tipo-momento" value="momento" checked>
          <label class="eg-seg-btn" for="tipo-momento">Por momento</label>

          <input type="radio" class="btn-check js-filter" name="tipo-dashboard" id="tipo-transcricao" value="transcricao">
          <label class="eg-seg-btn" for="tipo-transcricao">Transcrições</label>

          <input type="radio" class="btn-check js-filter" name="tipo-dashboard" id="tipo-universal" value="universal">
          <label class="eg-seg-btn" for="tipo-universal">Universais</label>
        </div>
      </div>
      <div class="row g-3 align-items-end">
        <div class="col-lg-3 col-md-6 filter-momento">
         <label class="eg-filter-label">Ação Pedagógica (Obrigatório)</label>
          <x-ui.select class="js-filter" id="f-evento">
            <option value="">-- Selecione uma ação --</option>
            @foreach($eventos as $evento)
            <option value="{{ $evento->id }}">{{ $evento->nome }}</option>
            @endforeach
          </x-ui.select>
        </div>
        <div class="col-lg-3 col-md-6 filter-universal d-none">
          <label class="eg-filter-label">Avaliação universal</label>
          <x-ui.select class="js-filter" id="f-avaliacao-universal">
            <option value="">-- Selecione uma avaliação --</option>
            @foreach($avaliacoesUniversais as $avaliacaoUniversal)
            <option value="{{ $avaliacaoUniversal->id }}">
              {{ $avaliacaoUniversal->descricao_universal ?: ($avaliacaoUniversal->templateAvaliacao->nome ?? 'Avaliação universal') }}
            </option>
            @endforeach
          </x-ui.select>
        </div>
        <div class="col-lg-3 col-md-6 filter-momento">
          <label class="eg-filter-label">Momento</label>
          <x-ui.select class="js-filter" id="f-atividade">
            <option value="">Primeiro selecione a ação pedagógica</option>
            @foreach($atividades as $atividade)
            @php
              $diaFormatado = $atividade->dia ? \Illuminate\Support\Carbon::parse($atividade->dia)->format('d/m') : '';
            @endphp
            <option value="{{ $atividade->id }}" data-evento-id="{{ $atividade->evento_id }}">
              {{ $atividade->descricao ?? 'Momento' }} - {{ $diaFormatado }} {{ $atividade->hora_inicio }}
            </option>
            @endforeach
          </x-ui.select>
        </div>
        <div class="col-lg-3 col-md-6">
          <div class="row g-3">
            <div class="col-6">
              <label class="eg-filter-label">De</label>
              <x-ui.input type="date" class="js-filter" id="f-de" />
            </div>
            <div class="col-6">
              <label class="eg-filter-label">Até</label>
              <x-ui.input type="date" class="js-filter" id="f-ate" />
            </div>
          </div>
        </div>
      </div>
  </x-ui.filter-card>

  <div id="dashboard-avaliacoes-notice" class="alert alert-info border-0 shadow-sm py-2 px-3 small mb-3 d-none" role="alert" aria-live="polite"></div>

  @if(request('fonte') === 'limesurvey')
    @include('dashboards.avaliacoes._filtros')
    @include('dashboards.avaliacoes._cards-totais')
    @include('dashboards.avaliacoes._bi-matriz')
  @else
    <div class="eg-stats-row" id="cards-totais">
      <x-ui.stat-card color="purple" icon="people-fill" label="Submissões" hint="Respostas completas registradas">
        <x-slot:value data-total="submissoes">-</x-slot:value>
      </x-ui.stat-card>
      <x-ui.stat-card color="blue" icon="list-check" label="Questões" hint="Com alguma resposta">
        <x-slot:value data-total="questoes">-</x-slot:value>
      </x-ui.stat-card>
      <x-ui.stat-card color="green" icon="calendar-event-fill">
        <x-slot:value data-total="eventos">-</x-slot:value>
        <x-slot:label data-total-label="eventos">Ações Pedagógicas</x-slot:label>
        <x-slot:hint data-total-help="eventos">Com respostas vinculadas</x-slot:hint>
      </x-ui.stat-card>
      <x-ui.stat-card color="orange" icon="clock-history" label="Última resposta" hint="Horário da última entrada" small>
        <x-slot:value data-total="ultima">-</x-slot:value>
      </x-ui.stat-card>
    </div>
  @endif

  <div class="row g-3">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 fw-bold mb-0">Distribuição por questão</h2>
        <span class="badge bg-primary-subtle text-primary" id="badge-pagina" style="display:none!important"></span>
      </div>
      <div class="row g-3" id="cards-questoes">
        <div class="col-12" id="placeholder-card">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted">
              Carregando gráficos...
            </div>
          </div>
        </div>
      </div>

      <nav id="paginacao-questoes" aria-label="Paginação de questões" class="mt-4" style="display:none">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div class="text-muted small" id="paginacao-info"></div>
          <ul class="pagination mb-0" id="paginacao-lista"></ul>
        </div>
      </nav>
    </div>
  </div>
</div>

@include('dashboards.avaliacoes._modal-respostas')

@push('styles')
<style>
  @media (min-width: 768px) {
    #cards-questoes > .col-md-6:only-child,
    #cards-questoes > .col-md-6:nth-child(odd):nth-last-child(1) {
      flex: 0 0 100%;
      max-width: 100%;
    }
  }
  #paginacao-questoes .page-link {
    color: #421944;
    border-color: #e2d5e8;
  }
  #paginacao-questoes .page-item.active .page-link {
    background-color: #421944;
    border-color: #421944;
    color: #fff;
  }
  #paginacao-questoes .page-item.disabled .page-link {
    color: #adb5bd;
  }

  /* ── Card de questão ─────────────────────────────────────── */
  .question-card {
    border: 1px solid rgba(66, 25, 68, .08);
    border-radius: 14px;
    transition: box-shadow .2s ease, transform .2s ease;
  }
  .question-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 .75rem 1.75rem rgba(66, 25, 68, .12) !important;
  }
  .question-card .card-body {
    padding: 1.25rem 1.35rem;
  }

  .question-head {
    display: flex;
    align-items: baseline;
    gap: .6rem;
  }
  .question-num {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.15rem;
    padding: .18rem .45rem;
    border-radius: 8px;
    background: linear-gradient(135deg, #421944, #6C345E);
    color: #fff;
    font-size: .78rem;
    font-weight: 700;
  }
  /* min-width:0 é o que permite o título usar toda a largura restante e quebrar */
  .question-title {
    flex: 1 1 auto;
    min-width: 0;
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.35;
    color: #2c2130;
  }

  .question-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .5rem;
    margin-top: .8rem;
    padding-top: .7rem;
    border-top: 1px solid rgba(66, 25, 68, .07);
  }
  .question-count {
    font-size: .8rem;
    color: #6b7280;
  }
  .question-tag {
    font-size: .68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: .22rem .6rem;
    border-radius: 999px;
    background: rgba(66, 25, 68, .07);
    color: #421944;
  }
  .question-card .question-controls {
    margin-left: auto;
  }
  .question-card .question-controls .form-select {
    border-radius: 8px;
  }
  .question-card .question-body {
    margin-top: 1rem;
  }

  /* ── Gráfico circular + legenda própria ──────────────────── */
  .chart-layout {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1.1rem;
  }
  .chart-canvas {
    flex: 1 1 250px;
    min-width: 0;
    height: 290px;
    position: relative;
  }
  .chart-legend {
    flex: 1 1 230px;
    min-width: 0;
    max-height: 290px;
    overflow-y: auto;
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: .2rem;
  }
  .chart-legend li {
    display: flex;
    align-items: flex-start;
    gap: .55rem;
    padding: .32rem .45rem;
    border-radius: 7px;
    font-size: .82rem;
    line-height: 1.35;
    transition: background .15s ease;
  }
  .chart-legend li:hover {
    background: rgba(66, 25, 68, .05);
  }
  .chart-legend li.is-empty {
    opacity: .45;
  }
  .chart-legend .dot {
    flex: 0 0 auto;
    width: .7rem;
    height: .7rem;
    border-radius: 3px;
    margin-top: .28rem;
  }
  .chart-legend .lbl {
    flex: 1 1 auto;
    min-width: 0;
    color: #374151;
  }
  .chart-legend .val {
    flex: 0 0 auto;
    font-weight: 700;
    color: #421944;
    white-space: nowrap;
  }
  .chart-legend .val em {
    font-style: normal;
    font-weight: 500;
    font-size: .9em;
    color: #9ca3af;
    margin-left: .25rem;
  }

  @media (max-width: 576px) {
    .question-card .question-controls {
      margin-left: 0;
      width: 100%;
    }
    .question-card .question-controls .form-select {
      width: 100%;
      max-width: none;
    }
  }
  @media (max-width: 576px) {
    #cards-questoes .question-header,
    #cards-questoes-momento .question-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.5rem;
    }
    #cards-questoes .question-controls,
    #cards-questoes-momento .question-controls {
      width: 100%;
      justify-content: flex-start;
    }
    #cards-questoes .question-controls select,
    #cards-questoes-momento .question-controls select {
      width: 100%;
      max-width: none;
    }
  }
</style>
@endpush

@push('scripts')
@vite('resources/js/dashboards/avaliacoes.js')
@endpush

<script>
(() => {
  const container = document.getElementById('avaliacoes-dashboard');
  if (!container) return;

  const endpoint = container.dataset.endpoint;
  const pdfEndpoint = "{{ route('dashboards.avaliacoes.pdf') }}";
  const btnExportarPdf = document.getElementById('btn-exportar-pdf');

  const filters = {
    tipoMomento: document.getElementById('tipo-momento'),
    tipoTranscricao: document.getElementById('tipo-transcricao'),
    tipoUniversal: document.getElementById('tipo-universal'),
    evento: document.getElementById('f-evento'),
    atividade: document.getElementById('f-atividade'),
    avaliacaoUniversal: document.getElementById('f-avaliacao-universal'),
    de: document.getElementById('f-de'),
    ate: document.getElementById('f-ate'),
  };
  const totalsEls = {
    submissoes: document.querySelector('[data-total=\"submissoes\"]'),
    questoes: document.querySelector('[data-total=\"questoes\"]'),
    eventos: document.querySelector('[data-total=\"eventos\"]'),
    ultima: document.querySelector('[data-total=\"ultima\"]'),
  };
  const totalLabels = {
    eventos: document.querySelector('[data-total-label=\"eventos\"]'),
  };
  const totalHelps = {
    eventos: document.querySelector('[data-total-help=\"eventos\"]'),
  };
  const cardsQuestoes = document.getElementById('cards-questoes');
  const chartInstances = new Map();
  const chartPreferences = new Map();
  const noticeEl = document.getElementById('dashboard-avaliacoes-notice');
  let cachedPerguntas = [];
  const atividadePlaceholder = filters.atividade?.querySelector('option[value=""]');
  const atividadeOptions = filters.atividade
    ? Array.from(filters.atividade.options).filter((option) => option.value !== '')
    : [];
  const textModalEl = document.getElementById('textAnswersModal');
  const textModalTitle = textModalEl?.querySelector('.js-text-modal-title');
  const textModalList = textModalEl?.querySelector('.js-text-modal-list');
  const textModalCount = textModalEl?.querySelector('.js-text-modal-count');
  let textModalInstance = null;
  const palette = ['#421944', '#008BBC', '#FDB913', '#E62270', '#2EB57D', '#601F69', '#6C345E', '#9602C7', '#A95DB1', '#D9A8E2', '#ECDEEC'];

  function currentTipo() {
    if (filters.tipoUniversal?.checked) return 'universal';
    if (filters.tipoTranscricao?.checked) return 'transcricao';
    return 'momento';
  }

  function buildParams() {
    const params = new URLSearchParams();
    const tipo = currentTipo();
    params.set('tipo', tipo);
    if (tipo === 'universal') {
      if (filters.avaliacaoUniversal?.value) params.set('avaliacao_id', filters.avaliacaoUniversal.value);
    } else {
      if (filters.evento.value) params.set('evento_id', filters.evento.value);
      if (filters.atividade.value) params.set('atividade_id', filters.atividade.value);
    }
    if (filters.de.value) params.set('de', filters.de.value);
    if (filters.ate.value) params.set('ate', filters.ate.value);
    return params.toString();
  }

  function clearNotice() {
    if (!noticeEl) return;

    noticeEl.classList.add('d-none');
    noticeEl.innerHTML = '';
  }

  function showNotice(type, title, messages) {
    if (!noticeEl) return;

    const items = Array.isArray(messages) ? messages.filter(Boolean) : [messages].filter(Boolean);
    if (items.length === 0) {
      clearNotice();
      return;
    }

    noticeEl.className = `alert alert-${type} border-0 shadow-sm py-2 px-3 small mb-3`;
    noticeEl.innerHTML = '';

    if (title) {
      const strong = document.createElement('strong');
      strong.className = 'd-block mb-1';
      strong.textContent = title;
      noticeEl.appendChild(strong);
    }

    if (items.length === 1) {
      const message = document.createElement('div');
      message.textContent = items[0];
      noticeEl.appendChild(message);
    } else {
      const list = document.createElement('ul');
      list.className = 'mb-0 ps-3';
      items.forEach((item) => {
        const li = document.createElement('li');
        li.textContent = item;
        list.appendChild(li);
      });
      noticeEl.appendChild(list);
    }

    noticeEl.classList.remove('d-none');
  }

  function getFilterValidation() {
    const tipo = currentTipo();
    const issues = [];

    if (tipo === 'universal') {
      if (!filters.avaliacaoUniversal?.value) {
        issues.push({
          level: 'info',
          title: 'Filtro obrigatório',
          message: 'Selecione uma avaliação universal para carregar os resultados.',
          blocking: true,
        });
      }
    } else if (!filters.evento?.value) {
      issues.push({
        level: 'info',
        title: 'Filtro obrigatório',
        message: 'Selecione uma ação pedagógica para carregar as avaliações.',
        blocking: true,
      });
    }

    if (filters.de?.value && filters.ate?.value && filters.de.value > filters.ate.value) {
      const inicio = filters.de.value;
      filters.de.value = filters.ate.value;
      filters.ate.value = inicio;
      issues.push({
        level: 'warning',
        title: 'Período ajustado',
        message: 'A data inicial era maior que a final, então o intervalo foi corrigido automaticamente.',
        blocking: false,
      });
    }

    if (tipo !== 'universal' && filters.evento?.value && filters.atividade?.value) {
      const selectedOption = filters.atividade.selectedOptions?.[0];
      if (selectedOption && selectedOption.dataset.eventoId && selectedOption.dataset.eventoId !== filters.evento.value) {
        filters.atividade.value = '';
        issues.push({
          level: 'warning',
          title: 'Momento ignorado',
          message: 'O momento selecionado não pertence à ação pedagógica escolhida e foi removido.',
          blocking: false,
        });
      }
    }

    return issues;
  }

  function renderEmptyState(message) {
    renderTotals({ submissoes: '-', questoes: '-', eventos: '-', ultima: '-' });
    cardsQuestoes.innerHTML = `
      <div class="col-12">
        <div class="card border-0 shadow-sm text-center py-5">
          <div class="card-body">
            <h5 class="fw-bold text-muted mb-2">Aguardando filtros</h5>
            <p class="text-muted mb-0">${message}</p>
          </div>
        </div>
      </div>`;
    paginacaoNav.style.display = 'none';
    badgePagina.style.setProperty('display', 'none', 'important');
  }

  function updateModeUi() {
    const tipo = currentTipo();
    const universal = tipo === 'universal';

    document.querySelectorAll('.filter-momento').forEach((el) => el.classList.toggle('d-none', universal));
    document.querySelectorAll('.filter-universal').forEach((el) => el.classList.toggle('d-none', !universal));

    if (filters.evento) filters.evento.disabled = universal;
    if (filters.atividade) filters.atividade.disabled = universal || !filters.evento.value;
    if (filters.avaliacaoUniversal) filters.avaliacaoUniversal.disabled = !universal;

    if (totalLabels.eventos) {
      totalLabels.eventos.textContent = universal ? 'Formulários universais' : 'Ações Pedagógicas';
    }
    if (totalHelps.eventos) {
      totalHelps.eventos.textContent = universal ? 'Com respostas registradas' : 'Com respostas vinculadas';
    }

    updateAtividadeFilter();
  }

  function updateAtividadeFilter() {
    if (!filters.evento || !filters.atividade) return;
    if (currentTipo() === 'universal') {
      filters.atividade.value = '';
      filters.atividade.disabled = true;
      return;
    }

    const eventoId = filters.evento.value;
    const hasEvento = eventoId !== '';

    filters.atividade.disabled = !hasEvento;

    if (atividadePlaceholder) {
      atividadePlaceholder.textContent = hasEvento ? 'Todas as atividades' : 'Primeiro selecione a ação pedagógica';
    }

    let selectedOptionVisible = !filters.atividade.value;
    atividadeOptions.forEach((option) => {
      const belongsToEvento = option.dataset.eventoId === eventoId;
      option.hidden = !hasEvento || !belongsToEvento;
      option.disabled = !hasEvento || !belongsToEvento;

      if (option.selected && belongsToEvento) {
        selectedOptionVisible = true;
      }
    });

    if (!hasEvento || !selectedOptionVisible) {
      filters.atividade.value = '';
    }
  }

  function setLoading(state) {
    if (state) {
      cardsQuestoes.innerHTML = `
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted">Carregando graficos...</div>
          </div>
        </div>`;
    }
  }

  function cleanText(value) {
    if (!value) return '';
    const text = String(value).replace(/<[^>]+>/g, ' ');
    return text.replace(/\s+/g, ' ').trim();
  }

  function openTextModal(pergunta, respostas) {
    const lista = Array.isArray(respostas) ? respostas : [];
    const titulo = cleanText(pergunta?.texto || 'Respostas');
    const total = lista.length;

    if (!textModalEl || !window.bootstrap?.Modal) {
      const texto = lista.length ? lista.map((resp) => `- ${cleanText(resp)}`).join('\n') : 'Sem respostas abertas.';
      alert(`${titulo}\n\n${texto}`);
      return;
    }

    if (!textModalInstance) {
      textModalInstance = new window.bootstrap.Modal(textModalEl);
    }

    if (textModalTitle) {
      textModalTitle.textContent = titulo;
    }

    if (textModalCount) {
      textModalCount.textContent = `${total} resposta(s)`;
    }

    if (textModalList) {
      textModalList.innerHTML = '';
      if (total === 0) {
        textModalList.innerHTML = '<div class=\"text-muted\">Sem respostas abertas.</div>';
      } else {
        lista.forEach((resp) => {
          const item = document.createElement('div');
          item.className = 'p-2 rounded border bg-light';
          item.textContent = cleanText(resp);
          textModalList.appendChild(item);
        });
      }
    }

    textModalInstance.show();
  }

  function renderTotals(totais) {
    totalsEls.submissoes.textContent = totais.submissoes === '-' ? '-' : new Intl.NumberFormat('pt-BR').format(totais.submissoes || 0);
    totalsEls.questoes.textContent = totais.questoes === '-' ? '-' : new Intl.NumberFormat('pt-BR').format(totais.questoes || 0);
    totalsEls.eventos.textContent = totais.eventos === '-' ? '-' : new Intl.NumberFormat('pt-BR').format(totais.eventos || 0);
    totalsEls.ultima.textContent = totais.ultima || '-';
  }

  function resolveChartType(pergunta, labels) {
    const userPref = chartPreferences.get(pergunta.id);
    if (userPref && userPref !== 'auto') return userPref;

    if (pergunta.tipo === 'boolean') return 'doughnut';
    if (pergunta.tipo === 'numero') return 'line';
    if (pergunta.tipo === 'escala') return 'bar';
    if (pergunta.tipo === 'unica') return 'bar';
    return labels.length > 3 ? 'polarArea' : 'bar';
  }

  function renderCharts(perguntas) {
    cachedPerguntas = perguntas;
    cardsQuestoes.innerHTML = '';
    if (!perguntas || perguntas.length === 0) {
      cardsQuestoes.innerHTML = `
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-muted text-center">Nenhuma avaliação encontrada para a combinação atual de filtros.</div>
          </div>
        </div>`;
      showNotice('info', 'Sem resultados', 'Não encontramos avaliações para essa combinação de filtros. Tente ampliar o período ou trocar a ação pedagógica.');
      return;
    }

    perguntas.forEach((pergunta) => {
      const totalRespostas = pergunta.total || 0;
      const titulo = cleanText(pergunta.texto);
      const resumo = cleanText(pergunta.resumo || '');

      const numeroMatch = /^(\d+(?:\.\d+)*)\s+([\s\S]+)$/.exec(String(titulo).trim());
      const numero = numeroMatch ? numeroMatch[1] : '';
      const tituloTexto = numeroMatch ? numeroMatch[2] : titulo;

      const wrapper = document.createElement('div');
      wrapper.className = 'col-12 col-lg-6';
      const card = document.createElement('div');
      card.className = 'card border-0 shadow-sm h-100 question-card';
      card.innerHTML = `
        <div class="card-body d-flex flex-column">
          <div class="question-head">
            ${numero ? `<span class="question-num">${numero}</span>` : ''}
            <h3 class="question-title"></h3>
          </div>
          <div class="question-meta">
            <span class="question-count">${totalRespostas} resposta(s)</span>
            ${resumo ? '<span class="question-tag"></span>' : ''}
            <div class="d-flex align-items-center gap-2 controls-slot question-controls"></div>
          </div>
          <div class="question-body"></div>
        </div>
      `;
      card.querySelector('.question-title').textContent = tituloTexto;
      if (resumo) card.querySelector('.question-tag').textContent = resumo;
      const body = card.querySelector('.question-body');
      const controlsSlot = card.querySelector('.controls-slot');

      const isText = pergunta.tipo === 'texto';
      const respostas = Array.isArray(pergunta.respostas) ? pergunta.respostas : [];
      const exemplos = Array.isArray(pergunta.exemplos) ? pergunta.exemplos : [];

      if (isText) {
        const listaFonte = respostas.length ? respostas : exemplos;
        const limitePreview = 5;

        const list = document.createElement('div');
        list.className = 'vstack gap-2';

        const itens = listaFonte.slice(0, limitePreview);
        if (itens.length === 0) {
          list.innerHTML = '<div class=\"text-muted\">Sem respostas abertas.</div>';
        } else {
          itens.forEach((resp) => {
            const item = document.createElement('div');
            item.className = 'p-2 rounded border bg-light';
            item.textContent = cleanText(resp);
            list.appendChild(item);
          });
        }

        if (listaFonte.length > limitePreview) {
          const hint = document.createElement('div');
          hint.className = 'text-muted small';
          hint.textContent = `Mostrando ${limitePreview} de ${listaFonte.length} resposta(s)`;
          list.appendChild(hint);
        }

        body.appendChild(list);

        if (listaFonte.length > limitePreview) {
          const toggleBtn = document.createElement('button');
          toggleBtn.type = 'button';
          toggleBtn.className = 'btn btn-outline-primary btn-sm align-self-start mt-1';
          toggleBtn.textContent = `Ver todas as respostas (${listaFonte.length})`;
          toggleBtn.addEventListener('click', () => openTextModal(pergunta, listaFonte));
          body.appendChild(toggleBtn);
        }
      } else {
        const canvas = document.createElement('canvas');
        canvas.height = 120;
        body.appendChild(canvas);

        if (chartInstances.has(pergunta.id)) {
          chartInstances.get(pergunta.id).destroy();
        }

        const labels = (pergunta.labels || []).map((label) => cleanText(label));
        const bg = labels.map((_, idx) => palette[idx % palette.length]);

        const typeOptions = [
          { value: 'auto', label: 'Auto' },
          { value: 'bar', label: 'Barras (vertical)' },
          { value: 'bar-horizontal', label: 'Barras (horizontal)' },
          { value: 'doughnut', label: 'Pizza' },
          { value: 'polarArea', label: 'Polar' },
          { value: 'line', label: 'Linha' },
        ];

        const userPref = chartPreferences.get(pergunta.id);
        const chartType = resolveChartType(pergunta, labels);

        if (controlsSlot) {
          const select = document.createElement('select');
          select.className = 'form-select form-select-sm';
          select.style.minWidth = '150px';
          typeOptions.forEach((opt) => {
            const option = document.createElement('option');
            option.value = opt.value;
            option.textContent = opt.label;
            select.appendChild(option);
          });
          select.value = userPref || 'auto';
          select.addEventListener('change', (event) => {
            const value = event.target.value;
            if (value === 'auto') {
              chartPreferences.delete(pergunta.id);
            } else {
              chartPreferences.set(pergunta.id, value);
            }
            renderCharts(cachedPerguntas);
          });
          controlsSlot.appendChild(select);
        }

        const baseChartType = chartType === 'bar-horizontal' ? 'bar' : chartType;
        const data = {
          labels,
          datasets: [{
            label: 'Respostas',
            data: pergunta.values,
            backgroundColor: baseChartType === 'line' ? 'rgba(66,25,68,0.15)' : bg,
            borderColor: palette[0],
            tension: 0.2,
            fill: baseChartType === 'line',
          }],
        };

        const options = {
          responsive: true,
          plugins: { legend: { display: false } },
          scales: {
            x: { ticks: { color: '#64748b' } },
            y: { ticks: { color: '#64748b', precision: 0 } },
          },
        };

        if (baseChartType === 'doughnut' || baseChartType === 'polarArea') {
          delete options.scales;
        }

        const autoHorizontal = !userPref && baseChartType === 'bar' && labels.length > 4;
        if (baseChartType === 'bar' && (chartType === 'bar-horizontal' || autoHorizontal)) {
          options.indexAxis = 'y';
        }

        const chart = new Chart(canvas, { type: baseChartType, data, options });
        chartInstances.set(pergunta.id, chart);
      }

      wrapper.appendChild(card);
      cardsQuestoes.appendChild(wrapper);
    });
  }

  const paginacaoNav = document.getElementById('paginacao-questoes');
  const paginacaoInfo = document.getElementById('paginacao-info');
  const paginacaoLista = document.getElementById('paginacao-lista');
  const badgePagina = document.getElementById('badge-pagina');

  let currentPage = 1;
  let lastMeta = null;

  function renderPagination(meta) {
    lastMeta = meta;
    if (!meta || meta.last_page <= 1) {
      paginacaoNav.style.display = 'none';
      badgePagina.style.setProperty('display', 'none', 'important');
      return;
    }

    paginacaoNav.style.display = '';
    badgePagina.style.setProperty('display', '', 'important');
    badgePagina.textContent = `Página ${meta.page} / ${meta.last_page}`;

    const start = (meta.page - 1) * meta.per_page + 1;
    const end = Math.min(meta.page * meta.per_page, meta.total);
    paginacaoInfo.textContent = `Mostrando questões ${start}–${end} de ${meta.total}`;

    paginacaoLista.innerHTML = '';

    const prevLi = document.createElement('li');
    prevLi.className = `page-item${meta.page <= 1 ? ' disabled' : ''}`;
    prevLi.innerHTML = `<button class="page-link" ${meta.page <= 1 ? 'disabled' : ''}>&lsaquo; Anterior</button>`;
    prevLi.querySelector('button').addEventListener('click', () => {
      if (meta.page > 1) loadData(meta.page - 1);
    });
    paginacaoLista.appendChild(prevLi);

    const maxButtons = 7;
    const half = Math.floor(maxButtons / 2);
    let pageStart = Math.max(1, meta.page - half);
    let pageEnd = Math.min(meta.last_page, pageStart + maxButtons - 1);
    if (pageEnd - pageStart < maxButtons - 1) {
      pageStart = Math.max(1, pageEnd - maxButtons + 1);
    }

    if (pageStart > 1) {
      appendPageBtn(1);
      if (pageStart > 2) appendEllipsis();
    }
    for (let p = pageStart; p <= pageEnd; p++) {
      appendPageBtn(p);
    }
    if (pageEnd < meta.last_page) {
      if (pageEnd < meta.last_page - 1) appendEllipsis();
      appendPageBtn(meta.last_page);
    }

    const nextLi = document.createElement('li');
    nextLi.className = `page-item${meta.page >= meta.last_page ? ' disabled' : ''}`;
    nextLi.innerHTML = `<button class="page-link" ${meta.page >= meta.last_page ? 'disabled' : ''}>Próximo &rsaquo;</button>`;
    nextLi.querySelector('button').addEventListener('click', () => {
      if (meta.page < meta.last_page) loadData(meta.page + 1);
    });
    paginacaoLista.appendChild(nextLi);
  }

  function appendPageBtn(p) {
    const li = document.createElement('li');
    li.className = `page-item${p === lastMeta?.page ? ' active' : ''}`;
    const btn = document.createElement('button');
    btn.className = 'page-link';
    btn.textContent = p;
    if (p === lastMeta?.page) btn.setAttribute('aria-current', 'page');
    btn.addEventListener('click', () => loadData(p));
    li.appendChild(btn);
    paginacaoLista.appendChild(li);
  }

  function appendEllipsis() {
    const li = document.createElement('li');
    li.className = 'page-item disabled';
    li.innerHTML = '<span class="page-link">&hellip;</span>';
    paginacaoLista.appendChild(li);
  }

  async function loadData(page = 1) {
    const validationIssues = getFilterValidation();
    const blockingIssue = validationIssues.find((issue) => issue.blocking);
    const nonBlockingIssues = validationIssues.filter((issue) => !issue.blocking);

    if (blockingIssue) {
      showNotice(blockingIssue.level, blockingIssue.title, blockingIssue.message);
      renderEmptyState(blockingIssue.message);
       return;
    }

    if (nonBlockingIssues.length > 0) {
      showNotice(nonBlockingIssues[0].level, nonBlockingIssues[0].title, nonBlockingIssues.map((issue) => issue.message));
    } else {
      clearNotice();
    }

    currentPage = page;
    setLoading(true);
    try {
      const url = `${endpoint}?${buildParams()}&page=${page}&per_page=50`;
      const response = await fetch(url, { headers: { Accept: 'application/json' } });

      if (!response.ok) {
        if (response.status === 422) {
          const payload = await response.json();
          const errors = Object.values(payload.errors || {}).flat().filter(Boolean);
          showNotice('danger', 'Filtro inválido', errors.length > 0 ? errors : ['Revise os campos selecionados e tente novamente.']);
          renderEmptyState('Revise os campos destacados e tente novamente.');
          return;
        }

        throw new Error(`HTTP ${response.status}`);
      }

      const payload = await response.json();

      renderTotals(payload.totais || {});
      renderCharts(payload.perguntas || []);
      renderPagination(payload.meta || null);

      if (payload.filtros) {
        if (!filters.de.value && payload.filtros.de) {
          filters.de.value = payload.filtros.de.split(' ')[0];
        }
        if (!filters.ate.value && payload.filtros.ate) {

      if ((payload.perguntas || []).length > 0 && nonBlockingIssues.length === 0) {
        clearNotice();
      }
          filters.ate.value = payload.filtros.ate.split(' ')[0];
        }
      }

      if (page > 1) {
      showNotice('danger', 'Falha ao carregar', 'Erro ao carregar os dados. Verifique a conexão e tente novamente.');
      cardsQuestoes.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body text-danger">Erro ao carregar dados. Verifique sua conexão.</div></div>';
      }
    } catch (error) {
      cardsQuestoes.innerHTML = '<div class=\"card border-0 shadow-sm\"><div class=\"card-body text-danger\">Erro ao carregar dados. Verifique sua conexão.</div></div>';
      paginacaoNav.style.display = 'none';
    }
  }

  updateModeUi();

  document.querySelectorAll('.js-filter').forEach((input) => {
    if (input === filters.evento) return;
    input.addEventListener('change', () => {
      if (input === filters.tipoMomento || input === filters.tipoUniversal) {
        updateModeUi();
      }
      loadData(1);
    });
  });
  filters.evento?.addEventListener('change', () => {
    updateAtividadeFilter();
    loadData(1);
  });
  
  //define a interface e chama o estado vazio
  updateModeUi();
  renderEmptyState('Selecione uma ação pedagógica ou uma avaliação universal para carregar os dados.');

  if (btnExportarPdf) {
    btnExportarPdf.addEventListener('click', () => {
      const validationIssues = getFilterValidation();
      const blockingIssue = validationIssues.find((issue) => issue.blocking);
      const nonBlockingIssues = validationIssues.filter((issue) => !issue.blocking);

      if (blockingIssue) {
        showNotice(blockingIssue.level, blockingIssue.title, blockingIssue.message);
        renderEmptyState(blockingIssue.message);
        return;
      }

      if (nonBlockingIssues.length > 0) {
        showNotice(nonBlockingIssues[0].level, nonBlockingIssues[0].title, nonBlockingIssues.map((issue) => issue.message));
      }

      const submissoesRaw = totalsEls.submissoes.textContent.replace(/[^\d]/g, '');
      const submissoes = parseInt(submissoesRaw) || 0;

      if (submissoes > 500) {
        const confirmMsg = `O relatório contém ${submissoes} submissões. ` +
                           `Gerar um PDF com muitos dados pode levar algum tempo e o arquivo pode ficar pesado. ` +
                           `Deseja continuar?`;
        if (!confirm(confirmMsg)) {
          return;
        }
      }

      const params = buildParams();
      window.open(`${pdfEndpoint}?${params}`, '_blank');
    });
  }

  loadData(1);
})();
</script>
@endsection
