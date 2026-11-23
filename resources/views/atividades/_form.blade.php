<style>
  .form-label[data-required="true"]::after {
    content: ' *';
    color: #dc3545;
    font-weight: 700;
  }

  .municipios-checkbox-list {
    max-height: 18rem;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 0.75rem;
    background: #fff;
  }

  .municipios-checkbox-item {
    padding: 0.35rem 0 0.35rem 1.5rem;
    border-bottom: 1px solid #f1f3f5;
  }

  .municipios-checkbox-item:last-child {
    border-bottom: 0;
  }

  .municipios-busca-wrap {
    position: relative;
  }

  .municipios-sugestoes {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    top: 100%;
    z-index: 1050;
    max-height: 320px;
    overflow-y: auto;
    margin-top: 2px;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    background: #fff;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
  }

  .municipios-sugestoes.is-open {
    display: block;
  }

  .municipios-sugestoes .sugestao-secao {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #6c757d;
    padding: 0.35rem 0.75rem;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
  }

  .municipios-sugestoes button.sugestao-item {
    display: block;
    width: 100%;
    text-align: left;
    border: 0;
    border-bottom: 1px solid #f1f3f5;
    padding: 0.45rem 0.75rem;
    font-size: 0.875rem;
    background: #fff;
    cursor: pointer;
  }

  .municipios-sugestoes button.sugestao-item:hover,
  .municipios-sugestoes button.sugestao-item:focus {
    background: #e7f1ff;
    outline: none;
  }

  .municipios-sugestoes button.sugestao-item:last-child {
    border-bottom: 0;
  }

  .municipios-resumo-panel {
    max-height: 22rem;
    overflow-y: auto;
    font-size: 0.8125rem;
  }

  .municipios-resumo-regiao {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #0d6efd;
    margin-top: 0.75rem;
    margin-bottom: 0.35rem;
  }

  .municipios-resumo-bloco:first-child .municipios-resumo-regiao {
    margin-top: 0;
  }

  .municipios-resumo-bloco {
    margin-bottom: 0.65rem;
  }

  .municipios-resumo-bloco:last-child {
    margin-bottom: 0;
  }

  .municipios-resumo-linha {
    padding: 0.2rem 0 0.2rem 0.35rem;
    color: #212529;
    border-bottom: 1px solid #f1f3f5;
  }

  .municipios-resumo-bloco .municipios-resumo-linha:last-child {
    border-bottom: 0;
  }

  .municipios-filtros-tags .badge {
    font-weight: 500;
  }
</style>

@csrf

{{-- Momento --}}
<div class="mb-3">
  <label for="descricao" class="form-label">Descrição</label>
  <textarea name="descricao" id="descricao" rows="3"
            class="form-control @error('descricao') is-invalid @enderror"
            required>{{ old('descricao', $atividade->descricao ?? '') }}</textarea>
  @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

@php
  $municipiosSelecionados = collect(old('municipios_enviados') ? old('municipios', []) : (isset($atividade) ? $atividade->municipios->pluck('id')->all() : []))
    ->map(fn($v) => (string) $v)
    ->all();
  $abrangenciaNacional = (bool) old('abrangencia_nacional', $atividade->abrangencia_nacional ?? false);
@endphp
<div class="mb-3">
  <span class="form-label d-block">Abrangência territorial</span>
  <input type="hidden" name="municipios_enviados" value="1">
  <input type="hidden" name="abrangencia_nacional" value="0">
  <div class="form-check border rounded p-3 ps-5 mb-3 bg-light">
    <input
      class="form-check-input"
      type="checkbox"
      name="abrangencia_nacional"
      value="1"
      id="abrangencia_nacional"
      @checked($abrangenciaNacional)>
    <label class="form-check-label fw-semibold" for="abrangencia_nacional">
      Brasil (abrangência nacional)
    </label>
    <div class="form-text">Use esta opção quando o momento puder receber participantes de qualquer município brasileiro.</div>
  </div>

  <script type="application/json" id="municipios-json-data">{!! json_encode($municipiosJson ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
  <div class="row g-3 align-items-start" id="municipios-selecao-container" @if($abrangenciaNacional) hidden @endif>
    <div class="col-lg-7">
      <div class="municipios-busca-wrap mb-2">
        <label for="municipios-busca-input" class="visually-hidden">Buscar pelo nome do município</label>
        <div class="input-group">
          <input type="text"
                 id="municipios-busca-input"
                 class="form-control"
                 placeholder="Nome do município, estado ou região"
                 autocomplete="off"
                 aria-autocomplete="list"
                 aria-controls="municipios-sugestoes-list">
          <button type="button"
                  class="btn btn-outline-secondary"
                  id="municipios-btn-limpar-filtros"
                  title="Limpar texto da busca e filtros de estado/região">
            Limpar filtros
          </button>
        </div>
        <div id="municipios-sugestoes-list"
             class="municipios-sugestoes"
             role="listbox"
             aria-label="Sugestões de busca"></div>
      </div>

      <div class="d-flex flex-wrap gap-2 mb-2 municipios-filtros-tags" id="municipios-filtros-tags" hidden></div>

      <div class="btn-toolbar flex-wrap gap-2 mb-2" role="toolbar" aria-label="Seleção em massa">
        <button type="button" class="btn btn-sm btn-outline-primary" id="municipios-btn-prioritarios">
          Selecionar municípios prioritários
        </button>
        <div class="btn-group btn-group-sm" role="group">
          <button type="button" class="btn btn-outline-primary" id="municipios-btn-selecionar-todos" title="Aplica aos municípios visíveis no filtro atual (ou a todos, sem filtro)">
            Selecionar filtrados
          </button>
          <button type="button" class="btn btn-outline-secondary" id="municipios-btn-desselecionar-filtrados">
            Desselecionar filtrados
          </button>
          <button type="button" class="btn btn-outline-secondary" id="municipios-btn-inverter-filtrados">
            Inverter filtrados
          </button>
        </div>
      </div>

      <div id="municipios-lista-checkboxes"
           class="municipios-checkbox-list @error('municipios') is-invalid @enderror @error('municipios.*') is-invalid @enderror"></div>
      <div id="municipios-hidden-inputs" class="d-none" aria-hidden="true"></div>

      <div class="form-text">Selecione um ou mais municípios atendidos por este momento. A opção de municípios prioritários substitui a seleção atual. A busca filtra pelo <strong>nome do município</strong>; as sugestões permitem filtrar por estado ou região.</div>
      @error('municipios') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
      @error('municipios.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-lg-5">
      <div class="border rounded bg-light h-100 d-flex flex-column">
        <div class="px-3 py-2 border-bottom small fw-semibold text-secondary d-flex justify-content-between align-items-center">
          <span>Selecionados</span>
          <span class="badge bg-secondary" id="municipios-resumo-contador">0</span>
        </div>
        <div class="p-3 municipios-resumo-panel flex-grow-1 text-secondary" id="municipios-resumo-lista">
          <span class="text-muted">Nenhum município selecionado.</span>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const dataEl = document.getElementById('municipios-json-data');
  if (!dataEl) return;

  let MUNICIPIOS = [];
  try {
    MUNICIPIOS = JSON.parse(dataEl.textContent || '[]');
  } catch (e) {
    MUNICIPIOS = [];
  }

  const inicialSelecionados = @json($municipiosSelecionados);

  const buscaInput = document.getElementById('municipios-busca-input');
  const sugestoesEl = document.getElementById('municipios-sugestoes-list');
  const listaEl = document.getElementById('municipios-lista-checkboxes');
  const hiddenEl = document.getElementById('municipios-hidden-inputs');
  const resumoLista = document.getElementById('municipios-resumo-lista');
  const resumoContador = document.getElementById('municipios-resumo-contador');
  const filtrosTags = document.getElementById('municipios-filtros-tags');
  const abrangenciaNacional = document.getElementById('abrangencia_nacional');
  const municipiosContainer = document.getElementById('municipios-selecao-container');

  if (!buscaInput || !listaEl || !hiddenEl) return;

  const selected = new Set(inicialSelecionados.map(String));
  let scopeEstado = null;
  let scopeRegiao = null;
  let somentePrioritarios = false;
  let query = buscaInput.value || '';
  let debounceTimer = null;

  const norm = (s) => String(s || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');

  function siglaEstado(m) {
    return m.estado && m.estado.sigla ? m.estado.sigla : '';
  }

  function nomeEstado(m) {
    return m.estado && m.estado.nome ? m.estado.nome : '';
  }

  function nomeRegiao(m) {
    return m.regiao && m.regiao.nome ? m.regiao.nome : '';
  }

  function linhaResumoMunicipio(m) {
    const uf = siglaEstado(m);
    return uf ? m.nome + ' - ' + uf : m.nome;
  }

  function getFilteredMunicipios() {
    let list = MUNICIPIOS.slice();
    if (somentePrioritarios) {
      list = list.filter((m) => m.prioritario);
    }
    if (scopeEstado) {
      const uf = String(scopeEstado).trim().toUpperCase();
      list = list.filter((m) => siglaEstado(m).trim().toUpperCase() === uf);
    }
    if (scopeRegiao) {
      const r = norm(String(scopeRegiao).trim());
      list = list.filter((m) => norm(String(nomeRegiao(m)).trim()) === r);
    }
    const q = norm(query);
    if (q) {
      list = list.filter((m) => norm(m.nome).includes(q));
    }
    list.sort((a, b) => String(a.nome).localeCompare(String(b.nome), 'pt-BR'));
    return list;
  }

  function syncHiddenInputs() {
    hiddenEl.innerHTML = '';
    selected.forEach((id) => {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'municipios[]';
      inp.value = id;
      hiddenEl.appendChild(inp);
    });
  }

  function restaurarListaSePrioritariosDesmarcados() {
    if (!somentePrioritarios || MUNICIPIOS.some((m) => m.prioritario && selected.has(m.id))) return;

    somentePrioritarios = false;
    scopeEstado = null;
    scopeRegiao = null;
    buscaInput.value = '';
    query = '';
    window.clearTimeout(debounceTimer);
    sugestoesEl.classList.remove('is-open');
    sugestoesEl.innerHTML = '';
    renderFiltrosTags();
    renderListaCheckboxes();
  }

  function renderResumo() {
    const byId = new Map(MUNICIPIOS.map((m) => [m.id, m]));
    const ids = [...selected].filter((id) => byId.has(id));

    resumoContador.textContent = String(ids.length);
    if (ids.length === 0) {
      resumoLista.innerHTML = '<span class="text-muted">Nenhum município selecionado.</span>';
      return;
    }

    const ordemRegiao = new Map();
    MUNICIPIOS.forEach((m, i) => {
      const r = nomeRegiao(m) || '(Sem região)';
      if (!ordemRegiao.has(r)) ordemRegiao.set(r, i);
    });

    const porRegiao = new Map();

    ids.forEach((id) => {
      const m = byId.get(id);
      if (!m) return;
      const regLabel = nomeRegiao(m) || '(Sem região)';
      if (!porRegiao.has(regLabel)) porRegiao.set(regLabel, []);
      porRegiao.get(regLabel).push(m);
    });

    const regioesSorted = [...porRegiao.keys()].sort((a, b) => {
      if (a === '(Sem região)') return 1;
      if (b === '(Sem região)') return -1;
      const oa = ordemRegiao.get(a) ?? 9999;
      const ob = ordemRegiao.get(b) ?? 9999;
      if (oa !== ob) return oa - ob;
      return a.localeCompare(b, 'pt-BR');
    });

    const frag = document.createDocumentFragment();

    regioesSorted.forEach((regLabel) => {
      const municipiosGrupo = porRegiao.get(regLabel).slice();
      municipiosGrupo.sort((a, b) =>
        linhaResumoMunicipio(a).localeCompare(linhaResumoMunicipio(b), 'pt-BR')
      );

      const bloco = document.createElement('div');
      bloco.className = 'municipios-resumo-bloco';

      const hReg = document.createElement('div');
      hReg.className = 'municipios-resumo-regiao';
      hReg.textContent = regLabel;
      bloco.appendChild(hReg);

      municipiosGrupo.forEach((m) => {
        const linha = document.createElement('div');
        linha.className = 'municipios-resumo-linha';
        linha.textContent = linhaResumoMunicipio(m);
        bloco.appendChild(linha);
      });
      frag.appendChild(bloco);
    });

    resumoLista.innerHTML = '';
    resumoLista.appendChild(frag);
  }

  function renderListaCheckboxes() {
    const filtrados = getFilteredMunicipios();
    listaEl.innerHTML = '';
    if (filtrados.length === 0) {
      listaEl.innerHTML = '<div class="text-muted small p-2">Nenhum município corresponde ao filtro atual.</div>';
      return;
    }
    filtrados.forEach((m) => {
      const wrap = document.createElement('div');
      wrap.className = 'form-check municipios-checkbox-item';
      const cb = document.createElement('input');
      cb.className = 'form-check-input';
      cb.type = 'checkbox';
      cb.id = 'municipio_cb_' + m.id;
      cb.checked = selected.has(m.id);
      cb.addEventListener('change', () => {
        if (cb.checked) selected.add(m.id);
        else selected.delete(m.id);
        syncHiddenInputs();
        renderResumo();
        if (!cb.checked) restaurarListaSePrioritariosDesmarcados();
      });
      const lab = document.createElement('label');
      lab.className = 'form-check-label small';
      lab.htmlFor = cb.id;
      lab.textContent = m.nome;
      wrap.appendChild(cb);
      wrap.appendChild(lab);
      listaEl.appendChild(wrap);
    });
  }

  function renderFiltrosTags() {
    filtrosTags.innerHTML = '';
    let has = false;
    if (somentePrioritarios) {
      has = true;
      const span = document.createElement('span');
      span.className = 'badge bg-primary d-inline-flex align-items-center gap-1';
      span.appendChild(document.createTextNode('Municípios prioritários '));
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn-close btn-close-white btn-sm p-0 ms-1';
      btn.style.fontSize = '0.6rem';
      btn.setAttribute('aria-label', 'Remover filtro de municípios prioritários');
      btn.addEventListener('click', () => {
        somentePrioritarios = false;
        renderFiltrosTags();
        renderListaCheckboxes();
        buildSugestoes();
      });
      span.appendChild(btn);
      filtrosTags.appendChild(span);
    }
    if (scopeEstado) {
      has = true;
      const span = document.createElement('span');
      span.className = 'badge bg-primary d-inline-flex align-items-center gap-1';
      span.appendChild(document.createTextNode('Estado: ' + scopeEstado + ' '));
      const btnEst = document.createElement('button');
      btnEst.type = 'button';
      btnEst.className = 'btn-close btn-close-white btn-sm p-0 ms-1';
      btnEst.style.fontSize = '0.6rem';
      btnEst.setAttribute('aria-label', 'Remover filtro de estado');
      btnEst.addEventListener('click', () => {
        scopeEstado = null;
        renderFiltrosTags();
        renderListaCheckboxes();
      });
      span.appendChild(btnEst);
      filtrosTags.appendChild(span);
    }
    if (scopeRegiao) {
      has = true;
      const span = document.createElement('span');
      span.className = 'badge bg-primary d-inline-flex align-items-center gap-1';
      span.appendChild(document.createTextNode('Região: ' + scopeRegiao + ' '));
      const btnReg = document.createElement('button');
      btnReg.type = 'button';
      btnReg.className = 'btn-close btn-close-white btn-sm p-0 ms-1';
      btnReg.style.fontSize = '0.6rem';
      btnReg.setAttribute('aria-label', 'Remover filtro de região');
      btnReg.addEventListener('click', () => {
        scopeRegiao = null;
        renderFiltrosTags();
        renderListaCheckboxes();
      });
      span.appendChild(btnReg);
      filtrosTags.appendChild(span);
    }
    filtrosTags.hidden = !has;
  }

  function buildSugestoes() {
    const q = norm(query);
    if (!q) {
      sugestoesEl.classList.remove('is-open');
      sugestoesEl.innerHTML = '';
      return;
    }

    const limMun = 12;
    const limEst = 8;
    const limReg = 6;

    const disponiveis = somentePrioritarios ? MUNICIPIOS.filter((m) => m.prioritario) : MUNICIPIOS;
    const munHits = disponiveis.filter((m) => norm(m.nome).includes(q))
      .sort((a, b) => String(a.nome).localeCompare(String(b.nome), 'pt-BR'))
      .slice(0, limMun);

    const estadosMap = new Map();
    disponiveis.forEach((m) => {
      const sigla = siglaEstado(m);
      if (sigla && !estadosMap.has(sigla)) {
        estadosMap.set(sigla, nomeEstado(m) || sigla);
      }
    });
    const estHits = [...estadosMap.entries()]
      .filter(([sigla, nome]) => norm(sigla).includes(q) || norm(nome).includes(q))
      .map(([sigla, nome]) => ({ sigla, nome }))
      .sort((a, b) => a.sigla.localeCompare(b.sigla, 'pt-BR'))
      .slice(0, limEst);

    const regSet = new Set(disponiveis.map((m) => nomeRegiao(m)).filter(Boolean));
    const regHits = [...regSet]
      .filter((nome) => norm(nome).includes(q))
      .sort((a, b) => a.localeCompare(b, 'pt-BR'))
      .slice(0, limReg);

    if (munHits.length === 0 && estHits.length === 0 && regHits.length === 0) {
      sugestoesEl.innerHTML = '<div class="px-3 py-2 small text-muted">Nenhuma sugestão.</div>';
      sugestoesEl.classList.add('is-open');
      return;
    }

    const parts = [];
    if (munHits.length) {
      parts.push('<div class="sugestao-secao">Municípios</div>');
      munHits.forEach((m) => {
        parts.push(
          '<button type="button" class="sugestao-item" data-kind="municipio" data-id="' +
            String(m.id).replace(/"/g, '&quot;') +
            '">' +
            String(m.nome).replace(/</g, '&lt;') +
            '</button>'
        );
      });
    }
    if (estHits.length) {
      parts.push('<div class="sugestao-secao">Estados (UF)</div>');
      estHits.forEach((e) => {
        parts.push(
          '<button type="button" class="sugestao-item" data-kind="estado" data-sigla="' +
            String(e.sigla).replace(/"/g, '&quot;') +
            '">' +
            (e.sigla + ' — ' + (e.nome || '')).replace(/</g, '&lt;') +
            '</button>'
        );
      });
    }
    if (regHits.length) {
      parts.push('<div class="sugestao-secao">Regiões</div>');
      regHits.forEach((nome) => {
        parts.push(
          '<button type="button" class="sugestao-item" data-kind="regiao" data-nome="' +
            String(nome).replace(/"/g, '&quot;') +
            '">' +
            nome.replace(/</g, '&lt;') +
            '</button>'
        );
      });
    }
    sugestoesEl.innerHTML = parts.join('');
    sugestoesEl.classList.add('is-open');

    sugestoesEl.querySelectorAll('.sugestao-item').forEach((btn) => {
      btn.addEventListener('click', () => {
        const kind = btn.getAttribute('data-kind');
        if (kind === 'municipio') {
          const id = btn.getAttribute('data-id');
          if (id) {
            selected.add(id);
            syncHiddenInputs();
            renderResumo();
            renderListaCheckboxes();
          }
        } else if (kind === 'estado') {
          const sigla = btn.getAttribute('data-sigla');
          scopeEstado = sigla || null;
          scopeRegiao = null;
          buscaInput.value = '';
          query = '';
          renderFiltrosTags();
          buildSugestoes();
          renderListaCheckboxes();
        } else if (kind === 'regiao') {
          const nome = btn.getAttribute('data-nome');
          scopeRegiao = nome || null;
          scopeEstado = null;
          buscaInput.value = '';
          query = '';
          renderFiltrosTags();
          buildSugestoes();
          renderListaCheckboxes();
        }
        sugestoesEl.classList.remove('is-open');
      });
    });
  }

  function onQueryInput() {
    query = buscaInput.value;
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => {
      buildSugestoes();
      renderListaCheckboxes();
    }, 80);
  }

  buscaInput.addEventListener('input', onQueryInput);
  buscaInput.addEventListener('focus', () => {
    query = buscaInput.value || '';
    buildSugestoes();
  });

  document.addEventListener('click', (ev) => {
    if (!buscaInput.closest('.municipios-busca-wrap')?.contains(ev.target)) {
      sugestoesEl.classList.remove('is-open');
    }
  });

  document.getElementById('municipios-btn-prioritarios')?.addEventListener('click', () => {
    somentePrioritarios = true;
    scopeEstado = null;
    scopeRegiao = null;
    buscaInput.value = '';
    query = '';
    sugestoesEl.classList.remove('is-open');
    sugestoesEl.innerHTML = '';
    selected.clear();
    MUNICIPIOS.filter((m) => m.prioritario).forEach((m) => selected.add(m.id));
    syncHiddenInputs();
    renderResumo();
    renderFiltrosTags();
    renderListaCheckboxes();
  });

  document.getElementById('municipios-btn-selecionar-todos')?.addEventListener('click', () => {
    getFilteredMunicipios().forEach((m) => selected.add(m.id));
    syncHiddenInputs();
    renderResumo();
    renderListaCheckboxes();
  });

  document.getElementById('municipios-btn-desselecionar-filtrados')?.addEventListener('click', () => {
    getFilteredMunicipios().forEach((m) => selected.delete(m.id));
    syncHiddenInputs();
    renderResumo();
    restaurarListaSePrioritariosDesmarcados();
    renderListaCheckboxes();
  });

  document.getElementById('municipios-btn-inverter-filtrados')?.addEventListener('click', () => {
    getFilteredMunicipios().forEach((m) => {
      if (selected.has(m.id)) selected.delete(m.id);
      else selected.add(m.id);
    });
    syncHiddenInputs();
    renderResumo();
    restaurarListaSePrioritariosDesmarcados();
    renderListaCheckboxes();
  });

  document.getElementById('municipios-btn-limpar-filtros')?.addEventListener('click', () => {
    scopeEstado = null;
    scopeRegiao = null;
    somentePrioritarios = false;
    buscaInput.value = '';
    query = '';
    sugestoesEl.classList.remove('is-open');
    sugestoesEl.innerHTML = '';
    renderFiltrosTags();
    renderListaCheckboxes();
    buildSugestoes();
  });

  function syncAbrangenciaNacional() {
    if (municipiosContainer && abrangenciaNacional) {
      municipiosContainer.hidden = abrangenciaNacional.checked;
    }
  }

  abrangenciaNacional?.addEventListener('change', syncAbrangenciaNacional);

  syncHiddenInputs();
  syncAbrangenciaNacional();
  renderFiltrosTags();
  renderResumo();
  renderListaCheckboxes();
})();
</script>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('descricao')?.closest('form');
    if (!form) return;

    const toggleAllMunicipios = document.getElementById('municipios_toggle_all');
    const municipioCheckboxes = Array.from(form.querySelectorAll('input[name="municipios[]"]'));

    // Remove a marcação anterior antes de aplicar o asterisco nos campos required.
    form.querySelectorAll('label[data-required="true"]').forEach(function (label) {
      label.removeAttribute('data-required');
    });

    // O asterisco visual passa a depender apenas do atributo HTML `required`.
    form.querySelectorAll('input[required], select[required], textarea[required]').forEach(function (field) {
      if (!field.id) return;
      const label = form.querySelector(`label[for="${field.id}"]`);
      if (label) {
        label.dataset.required = 'true';
      }
    });

    if (!toggleAllMunicipios || municipioCheckboxes.length === 0) {
      return;
    }

    const syncToggleAllMunicipios = function () {
      const checkedCount = municipioCheckboxes.filter(function (checkbox) {
        return checkbox.checked;
      }).length;

      toggleAllMunicipios.checked = checkedCount === municipioCheckboxes.length;
      toggleAllMunicipios.indeterminate = checkedCount > 0 && checkedCount < municipioCheckboxes.length;
    };

    toggleAllMunicipios.addEventListener('change', function () {
      municipioCheckboxes.forEach(function (checkbox) {
        checkbox.checked = toggleAllMunicipios.checked;
      });
      toggleAllMunicipios.indeterminate = false;
    });

    municipioCheckboxes.forEach(function (checkbox) {
      checkbox.addEventListener('change', syncToggleAllMunicipios);
    });

    syncToggleAllMunicipios();
  });
</script>

<div class="row g-3">
  <div class="col-md-4">
    <label for="dia" class="form-label">Dia</label>
    <input type="date" name="dia" id="dia"
           value="{{ old('dia', isset($atividade)? $atividade->dia : '') }}"
           class="form-control @error('dia') is-invalid @enderror" required>
    @error('dia') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-4">
    <label for="hora_inicio" class="form-label">Hora de início</label>
    <input type="time" name="hora_inicio" id="hora_inicio"
           value="{{ old('hora_inicio', isset($atividade)? \Illuminate\Support\Str::of($atividade->hora_inicio)->substr(0,5) : '') }}"
           class="form-control @error('hora_inicio') is-invalid @enderror" required>
    @error('hora_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-4">
    <label for="hora_fim" class="form-label">Hora de término</label>
    <input type="time" name="hora_fim" id="hora_fim"
           value="{{ old('hora_fim', isset($atividade)? \Illuminate\Support\Str::of($atividade->hora_fim)->substr(0,5) : '') }}"
           class="form-control @error('hora_fim') is-invalid @enderror" required>
    @error('hora_fim') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-md-6">
    <label class="form-label">Público esperado</label>
    <input type="number" name="publico_esperado" min="0" step="1"
           value="{{ old('publico_esperado', $atividade->publico_esperado ?? '') }}"
           class="form-control @error('publico_esperado') is-invalid @enderror"
           placeholder="Quantas pessoas pretende alcançar">
    @error('publico_esperado') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  <div class="col-md-6">
    <label class="form-label">Carga horária</label>
    <div class="row g-2 align-items-end">
      <div class="col-6">
        <label for="carga_horas" class="form-label small text-muted mb-0">Horas</label>
        <input type="number" name="carga_horas" id="carga_horas" min="0" step="1"
               value="{{ old('carga_horas', isset($atividade) && $atividade->carga_horaria !== null ? intdiv($atividade->carga_horaria, 60) : 0) }}"
               class="form-control @error('carga_horas') is-invalid @enderror"
               placeholder="0">
        @error('carga_horas') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>
      <div class="col-6">
        <label for="carga_minutos" class="form-label small text-muted mb-0">Minutos</label>
        <input type="number" name="carga_minutos" id="carga_minutos" min="0" max="59" step="1"
               value="{{ old('carga_minutos', isset($atividade) && $atividade->carga_horaria !== null ? $atividade->carga_horaria % 60 : 0) }}"
               class="form-control @error('carga_minutos') is-invalid @enderror"
               placeholder="0">
        @error('carga_minutos') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>
    </div>
  </div>
</div>

@can('presenca.abrir')
  @php
    $valorAbreEm = old('presenca_abre_em', isset($atividade) ? \App\Support\AgendamentoPresenca::paraInput($atividade->presenca_abre_em) : null);
    $valorFechaEm = old('presenca_fecha_em', isset($atividade) ? \App\Support\AgendamentoPresenca::paraInput($atividade->presenca_fecha_em) : null);
    $agendamentoAtivo = (bool) old('agendamento_presenca_ativo', $valorAbreEm || $valorFechaEm);
    if (! $agendamentoAtivo) {
      $valorAbreEm = null;
      $valorFechaEm = null;
    }
  @endphp
  <div class="border rounded p-3 mt-3 bg-light">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <label class="form-label fw-bold mb-0 text-engaja">
        <i class="bi bi-clock-history me-1"></i> Confirmação de presença — Agendamento de abertura/fechamento
      </label>
    </div>
    <x-agendamento-presenca id="agendamento_presenca_ativo" :ativo="$agendamentoAtivo">
      <div class="btn-group btn-group-sm mb-3">
        <button type="button" class="btn btn-outline-secondary" id="btn-preencher-agendamento-sugestao" title="Preenche os campos com a data e horários do momento">
          Preencher com dia/horário do momento
        </button>
        <button type="button" class="btn btn-outline-secondary" id="btn-limpar-agendamento-form" title="Limpa os campos de agendamento">
          Limpar agendamento
        </button>
      </div>

    <div class="row g-3">
      <div class="col-md-6">
        <label for="presenca_abre_em" class="form-label">Abrir presença em</label>
        <input type="datetime-local" name="presenca_abre_em" id="presenca_abre_em"
               value="{{ $valorAbreEm }}"
               class="form-control @error('presenca_abre_em') is-invalid @enderror">
        @error('presenca_abre_em') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>

      <div class="col-md-6">
        <label for="presenca_fecha_em" class="form-label">Fechar presença em</label>
        <input type="datetime-local" name="presenca_fecha_em" id="presenca_fecha_em"
               value="{{ $valorFechaEm }}"
               class="form-control @error('presenca_fecha_em') is-invalid @enderror">
        @error('presenca_fecha_em') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>
    </div>
    </x-agendamento-presenca>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const btnSugestao = document.getElementById('btn-preencher-agendamento-sugestao');
      const btnLimpar = document.getElementById('btn-limpar-agendamento-form');
      const inputDia = document.getElementById('dia');
      const inputHoraInicio = document.getElementById('hora_inicio');
      const inputHoraFim = document.getElementById('hora_fim');
      const inputAbre = document.getElementById('presenca_abre_em');
      const inputFecha = document.getElementById('presenca_fecha_em');

      function formatarDateTime(dia, hora) {
        if (!dia || !hora) return '';
        const h = hora.length >= 5 ? hora.substring(0, 5) : hora;
        // Horário (Brasília, UTC-3) que já passou não é sugerido: evitaria agendar um
        // fechamento/abertura no passado e alterar o estado da presença sem querer.
        if (new Date(`${dia}T${h}:00-03:00`).getTime() <= Date.now()) return '';
        return `${dia}T${h}`;
      }

      function aplicarSugestao() {
        if (!inputDia || !inputAbre || !inputFecha) return;
        const dia = inputDia.value;
        const hIni = inputHoraInicio?.value;
        const hFim = inputHoraFim?.value;

        inputAbre.value = formatarDateTime(dia, hIni);
        inputFecha.value = formatarDateTime(dia, hFim);
        inputAbre.dispatchEvent(new Event('input', { bubbles: true }));
        inputFecha.dispatchEvent(new Event('input', { bubbles: true }));
      }

      btnSugestao?.addEventListener('click', aplicarSugestao);

      btnLimpar?.addEventListener('click', function () {
        const toggle = document.getElementById('agendamento_presenca_ativo');
        toggle.checked = false;
        toggle.dispatchEvent(new Event('change'));
      });
    });
  </script>
@endcan

@php
  $listaCopiaveis = collect($atividadesCopiaveis ?? []);
@endphp
@if($listaCopiaveis->isNotEmpty())
  <div class="mt-3">
    <label for="copiar_inscritos_de" class="form-label">Importar inscritos</label>
    <select name="copiar_inscritos_de" id="copiar_inscritos_de" class="form-select @error('copiar_inscritos_de') is-invalid @enderror">
      <option value="">Não importar inscritos</option>
      @foreach($listaCopiaveis as $momentoCopiavel)
        @php
          $eventoNome = $momentoCopiavel->evento->nome ?? 'Evento sem título';
          $descricao = $momentoCopiavel->descricao ?: 'Momento';
          $dia = $momentoCopiavel->dia ? \Carbon\Carbon::parse($momentoCopiavel->dia)->format('d/m/Y') : 'Sem data';
          $hora = $momentoCopiavel->hora_inicio ? \Carbon\Carbon::parse($momentoCopiavel->hora_inicio)->format('H:i') : null;
          $inscritos = $momentoCopiavel->inscricoes_count ?? $momentoCopiavel->inscricoes()->count();
          $label = $eventoNome . ' - ' . $descricao . ' (' . $dia . ($hora ? ' • ' . $hora : '') . ') - ' . $inscritos . ' inscrito' . ($inscritos == 1 ? '' : 's');
        @endphp
        <option value="{{ $momentoCopiavel->id }}" @selected(old('copiar_inscritos_de') == $momentoCopiavel->id)>
          {{ $label }}
        </option>
      @endforeach
    </select>
    <div class="form-text">Duplicaremos todos os participantes desse momento no ato do salvamento.</div>
    @error('copiar_inscritos_de') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>
@endif

<div class="d-flex justify-content-end gap-2 mt-3">
  <a href="{{ route('eventos.atividades.index', $evento) }}" class="btn btn-outline-secondary">Cancelar</a>
  <button class="btn btn-engaja">{{ $submitLabel ?? 'Salvar' }}</button>
</div>
