import Chart from 'chart.js/auto';

// O script inline legado de dashboards/avaliacoes.blade.php usa `new Chart` global.
// Expor aqui substitui a tag CDN do Chart.js que existia na view.
window.Chart = Chart;

const PALETTE = [
  '#421944', '#008BBC', '#FDB913', '#E62270', '#2EB57D',
  '#601F69', '#6C345E', '#9602C7', '#A95DB1', '#D9A8E2', '#ECDEEC',
];

const fmt = new Intl.NumberFormat('pt-BR');

// ─── Helpers ───────────────────────────────────────────────

function cleanText(value) {
  if (!value) return '';
  return String(value).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

function bg(index) {
  return PALETTE[index % PALETTE.length];
}

function destroyChart(map, key) {
  if (map.has(key)) {
    map.get(key).destroy();
    map.delete(key);
  }
}

function makeSelect(className, minWidth, options, value, onChange) {
  const select = document.createElement('select');
  select.className = className;
  select.style.minWidth = minWidth;
  options.forEach(([val, label]) => {
    const opt = document.createElement('option');
    opt.value = val;
    opt.textContent = label;
    select.appendChild(opt);
  });
  select.value = value;
  select.addEventListener('change', onChange);
  return select;
}

function createCanvas(parent, height = 120) {
  const canvas = document.createElement('canvas');
  canvas.height = height;
  parent.appendChild(canvas);
  return canvas;
}

/**
 * Gráficos circulares usam legenda em HTML ao lado do canvas: a legenda nativa do
 * Chart.js corta rótulos longos na borda do canvas, e aqui ainda cabem contagem e
 * percentual. Devolve o container do canvas, já dimensionado pelo CSS.
 */
function buildCircularChartLayout(body) {
  const layout = document.createElement('div');
  layout.className = 'chart-layout';
  const canvasBox = document.createElement('div');
  canvasBox.className = 'chart-canvas';
  const legend = document.createElement('ul');
  legend.className = 'chart-legend';
  layout.appendChild(canvasBox);
  layout.appendChild(legend);
  body.appendChild(layout);
  return { canvasBox, legend };
}

function fillChartLegend(legend, items, chart) {
  const total = items.reduce((soma, item) => soma + item.value, 0);

  items.forEach((item, index) => {
    const percentual = total ? Math.round((item.value / total) * 100) : 0;
    const li = document.createElement('li');
    if (!item.value) li.classList.add('is-empty');

    const dot = document.createElement('span');
    dot.className = 'dot';
    dot.style.background = item.color;

    const label = document.createElement('span');
    label.className = 'lbl';
    label.textContent = item.label;

    const valor = document.createElement('span');
    valor.className = 'val';
    valor.textContent = String(item.value);
    const pct = document.createElement('em');
    pct.textContent = `${percentual}%`;
    valor.appendChild(pct);

    li.append(dot, label, valor);

    // Destaca a fatia correspondente ao passar o mouse na linha da legenda.
    li.addEventListener('mouseenter', () => {
      chart.setActiveElements([{ datasetIndex: 0, index }]);
      chart.update('none');
    });
    li.addEventListener('mouseleave', () => {
      chart.setActiveElements([]);
      chart.update('none');
    });

    legend.appendChild(li);
  });
}

// Os enunciados vêm prefixados pela numeração da questão ("15 O Plano de Cargos…",
// "17.1 Ano:"). Separá-la deixa o título livre para ocupar a largura inteira do card.
function splitQuestionNumber(titulo) {
  const match = /^(\d+(?:\.\d+)*)\s+([\s\S]+)$/.exec(String(titulo).trim());
  return match ? { numero: match[1], texto: match[2] } : { numero: '', texto: titulo };
}

function buildCardShell(titulo, totalRespostas, resumo) {
  const { numero, texto } = splitQuestionNumber(titulo);
  const wrapper = document.createElement('div');
  wrapper.className = 'col-12';
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
  card.querySelector('.question-title').textContent = texto;
  if (resumo) card.querySelector('.question-tag').textContent = resumo;
  wrapper.appendChild(card);
  return {
    wrapper,
    body: card.querySelector('.question-body'),
    controls: card.querySelector('.controls-slot'),
  };
}

function buildCardShellHalf(titulo, totalRespostas, resumo) {
  const shell = buildCardShell(titulo, totalRespostas, resumo);
  shell.wrapper.className = 'col-12 col-lg-6';
  return shell;
}

// ─── Aggregação yearsMap (compartilhada entre matrix block e bi matrizes) ───

function aggregateYearsMap(matriz, linhaCodigo, medida) {
  if (linhaCodigo !== '__ALL__') {
    return matriz?.valores?.[linhaCodigo]?.[medida] || {};
  }
  const result = {};
  (matriz.linhas || []).forEach(({ codigo }) => {
    const byYear = matriz?.valores?.[codigo]?.[medida] || {};
    Object.entries(byYear).forEach(([ano, byMunicipio]) => {
      if (!result[ano]) result[ano] = {};
      Object.entries(byMunicipio || {}).forEach(([municipio, valor]) => {
        result[ano][municipio] = Number(result[ano][municipio] || 0) + Number(valor || 0);
      });
    });
  });
  return result;
}

function extractMunicipios(yearsMap, anos) {
  const set = new Set();
  anos.forEach((ano) => {
    Object.keys(yearsMap[ano] || {}).forEach((m) => set.add(m));
  });
  return Array.from(set).sort((a, b) => a.localeCompare(b, 'pt-BR', { sensitivity: 'base' }));
}

function buildYearDatasets(yearsMap, anos, municipios) {
  return anos.map((ano, idx) => ({
    label: ano,
    data: municipios.map((m) => Number((yearsMap[ano] || {})[m] || 0)),
    backgroundColor: bg(idx),
    borderColor: bg(idx),
    tension: 0.2,
  }));
}

// ─── Text Answers helpers ───────────────────────────────────

function extractResposta(item) {
  if (item && typeof item === 'object') {
    return { texto: cleanText(item.texto || ''), municipio: cleanText(item.municipio || '') };
  }
  return { texto: cleanText(item || ''), municipio: '' };
}

function buildRespostaItem(item) {
  const { texto, municipio } = extractResposta(item);
  const el = document.createElement('div');
  el.className = 'p-2 rounded border bg-light';
  if (municipio && municipio !== 'Não informado') {
    el.innerHTML = `<div class="mb-1"><span class="badge bg-secondary-subtle text-secondary" style="font-size:.75em">${municipio}</span></div>${texto}`;
  } else {
    el.textContent = texto;
  }
  return el;
}

// ─── Text Answers Modal ─────────────────────────────────────

function createTextModal() {
  const el = document.getElementById('textAnswersModal');
  if (!el) return { open() {} };

  const titleEl = el.querySelector('.js-text-modal-title');
  const listEl = el.querySelector('.js-text-modal-list');
  const countEl = el.querySelector('.js-text-modal-count');
  let instance = null;

  return {
    open(pergunta, respostas) {
      const lista = Array.isArray(respostas) ? respostas : [];
      const titulo = cleanText(pergunta?.texto || 'Respostas');

      if (!window.bootstrap?.Modal) {
        alert(`${titulo}\n\n${lista.map((r) => `- ${extractResposta(r).texto}`).join('\n') || 'Sem respostas abertas.'}`);
        return;
      }

      if (!instance) instance = new window.bootstrap.Modal(el);
      if (titleEl) titleEl.textContent = titulo;
      if (countEl) countEl.textContent = `${lista.length} resposta(s)`;

      if (listEl) {
        listEl.innerHTML = '';
        if (!lista.length) {
          listEl.innerHTML = '<div class="text-muted">Sem respostas abertas.</div>';
        } else {
          lista.forEach((resp) => listEl.appendChild(buildRespostaItem(resp)));
        }
      }

      instance.show();
    },
  };
}

// ─── Chart type resolution ──────────────────────────────────

function resolveChartType(pergunta, labels, userPref) {
  if (userPref && userPref !== 'auto') return userPref;
  if (pergunta.tipo === 'boolean') return 'doughnut';
  if (pergunta.tipo === 'numero') return 'line';
  if (pergunta.tipo === 'escala') return 'bar';
  return labels.length > 3 ? 'polarArea' : 'bar';
}

// ─── Sub-renderers for renderSimpleQuestionCard ─────────────

function renderTextQuestion(body, pergunta, listaFonte, modal) {
  const PREVIEW = 5;
  const list = document.createElement('div');
  list.className = 'vstack gap-2';

  const itens = listaFonte.slice(0, PREVIEW);
  if (!itens.length) {
    list.innerHTML = '<div class="text-muted">Sem respostas abertas.</div>';
  } else {
    itens.forEach((resp) => list.appendChild(buildRespostaItem(resp)));
  }

  if (listaFonte.length > PREVIEW) {
    const hint = document.createElement('div');
    hint.className = 'text-muted small';
    hint.textContent = `Mostrando ${PREVIEW} de ${listaFonte.length} resposta(s)`;
    list.appendChild(hint);
  }
  body.appendChild(list);

  if (listaFonte.length > PREVIEW) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline-primary btn-sm align-self-start mt-1';
    btn.textContent = `Ver todas as respostas (${listaFonte.length})`;
    btn.addEventListener('click', () => modal.open(pergunta, listaFonte));
    body.appendChild(btn);
  }
}

function renderGenericChart(body, controls, pergunta, chartInstances, chartPreferences, rerender) {
  destroyChart(chartInstances, pergunta.id);

  const labels = (pergunta.labels || []).map(cleanText);
  const colors = labels.map((_, i) => bg(i));
  const typeOptions = [
    ['auto', 'Auto'], ['bar', 'Barras (vertical)'], ['bar-horizontal', 'Barras (horizontal)'],
    ['doughnut', 'Pizza'], ['polarArea', 'Polar'], ['line', 'Linha'],
  ];

  const userPref = chartPreferences.get(pergunta.id);
  const chartType = resolveChartType(pergunta, labels, userPref);
  const base = chartType === 'bar-horizontal' ? 'bar' : chartType;
  const isCircular = base === 'doughnut' || base === 'polarArea';

  const layout = isCircular ? buildCircularChartLayout(body) : null;
  const canvas = createCanvas(layout ? layout.canvasBox : body, isCircular ? 290 : 120);

  if (controls) {
    controls.appendChild(makeSelect(
      'form-select form-select-sm', '150px', typeOptions, userPref || 'auto',
      (e) => {
        if (e.target.value === 'auto') chartPreferences.delete(pergunta.id);
        else chartPreferences.set(pergunta.id, e.target.value);
        rerender();
      },
    ));
  }

  const data = {
    labels,
    datasets: [{
      label: 'Respostas',
      data: pergunta.values,
      backgroundColor: base === 'line' ? 'rgba(66,25,68,0.15)' : colors,
      borderColor: PALETTE[0],
      borderWidth: isCircular ? 2 : 1,
      hoverOffset: isCircular ? 8 : 0,
      tension: 0.2,
      fill: base === 'line',
    }],
  };

  const options = {
    responsive: true,
    maintainAspectRatio: !isCircular,
    plugins: { legend: { display: false } },
    scales: {
      x: { ticks: { color: '#64748b' } },
      y: { ticks: { color: '#64748b', precision: 0 } },
    },
  };
  if (isCircular) delete options.scales;
  const autoH = !userPref && base === 'bar' && labels.length > 4;
  if (base === 'bar' && (chartType === 'bar-horizontal' || autoH)) options.indexAxis = 'y';

  const chart = new Chart(canvas, { type: base, data, options });
  chartInstances.set(pergunta.id, chart);

  if (layout) {
    fillChartLegend(
      layout.legend,
      labels.map((label, i) => ({ label, value: Number((pergunta.values || [])[i] || 0), color: colors[i] })),
      chart,
    );
  }
}

// Tipo padrão dos gráficos de classificação por município (ver renderMunicipioLevel).
const LEVEL_CHART_DEFAULT = 'doughnut';

// O tooltip do Chart.js não quebra linha sozinho: textos longos precisam virar
// um array de linhas curtas, senão vazam para fora do gráfico.
function wrapLines(texto, largura = 60) {
  const palavras = cleanText(texto).split(' ').filter(Boolean);
  if (!palavras.length) return [];

  return palavras.reduce((linhas, palavra) => {
    const atual = linhas[linhas.length - 1];
    if (atual && `${atual} ${palavra}`.length <= largura) {
      linhas[linhas.length - 1] = `${atual} ${palavra}`;
    } else {
      linhas.push(palavra);
    }
    return linhas;
  }, []);
}

function levelChartType(pergunta, chartPreferences) {
  return chartPreferences.get(`${pergunta.id}::level_type`) || LEVEL_CHART_DEFAULT;
}

function renderMunicipioLevel(body, controls, pergunta, chartInstances, chartPreferences, rerender) {
  const chartKey = `${pergunta.id}::level`;
  const prefKey = `${pergunta.id}::level_type`;
  const grupos = Array.isArray(pergunta.grupos_nivel) ? pergunta.grupos_nivel : [];
  const labels = grupos.map((g) => cleanText(g.label));
  const values = grupos.map((g) => (Array.isArray(g.municipios) ? g.municipios.length : 0));
  const municipiosPorGrupo = grupos.map((g) => (Array.isArray(g.municipios) ? g.municipios.map(cleanText) : []));
  const descricoes = grupos.map((g) => wrapLines(g.descricao || ''));

  if (!labels.length) {
    body.innerHTML = '<div class="text-muted">Sem dados para esta questão.</div>';
    return;
  }

  if (controls) {
    controls.appendChild(makeSelect(
      'form-select form-select-sm', '170px',
      [['doughnut', 'Pizza'], ['bar-horizontal', 'Barras (horizontal)'], ['bar', 'Barras (vertical)']],
      levelChartType(pergunta, chartPreferences),
      (e) => { chartPreferences.set(prefKey, e.target.value); rerender(); },
    ));
  }

  const selected = levelChartType(pergunta, chartPreferences);
  const isCircular = selected === 'doughnut';
  const horizontal = selected === 'bar-horizontal';

  const layout = isCircular ? buildCircularChartLayout(body) : null;
  const canvas = createCanvas(layout ? layout.canvasBox : body, isCircular ? 290 : 120);
  destroyChart(chartInstances, chartKey);

  // A legenda textual "1 — Semanal, 2 — Quinzenal..." era necessária quando o eixo
  // mostrava o código numérico; agora os próprios rótulos são as classificações.
  const options = {
    responsive: true,
    maintainAspectRatio: !isCircular,
    plugins: {
      legend: { display: false },
      title: { display: !isCircular, text: 'Municípios por classificação' },
      tooltip: {
        callbacks: {
          label: (ctx) => {
            const total = isCircular ? ctx.parsed : (horizontal ? ctx.parsed.x : ctx.parsed.y);
            return `${total} município(s)`;
          },
          afterLabel: (ctx) => {
            const descricao = descricoes[ctx.dataIndex] || [];
            const municipios = municipiosPorGrupo[ctx.dataIndex] || [];
            return descricao.length ? [...descricao, '', ...municipios] : municipios;
          },
        },
      },
    },
  };

  if (!isCircular) {
    options.scales = {
      x: { ticks: { color: '#64748b', maxRotation: 50, minRotation: 25 } },
      y: { ticks: { color: '#64748b', precision: 0 } },
    };
    options.indexAxis = horizontal ? 'y' : 'x';
  }

  const chart = new Chart(canvas, {
    type: isCircular ? 'doughnut' : 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Municípios',
        data: values,
        backgroundColor: labels.map((_, i) => bg(i)),
        borderColor: labels.map((_, i) => bg(i)),
        borderWidth: isCircular ? 2 : 0,
        hoverOffset: isCircular ? 8 : 0,
      }],
    },
    options,
  });
  chartInstances.set(chartKey, chart);

  if (layout) {
    fillChartLegend(
      layout.legend,
      labels.map((label, i) => ({ label, value: values[i], color: bg(i) })),
      chart,
    );
  }
}

function renderMunicipioMultiselect(body, controls, pergunta, chartInstances, chartPreferences, rerender) {
  const totalsKey = `${pergunta.id}::totais`;
  const munKey = `${pergunta.id}::municipios`;
  const prefKey = `${pergunta.id}::multiselect_mode`;
  const stackMode = chartPreferences.get(prefKey) || 'stacked';

  if (controls) {
    controls.appendChild(makeSelect(
      'form-select form-select-sm', '170px',
      [['stacked', 'Composição empilhada'], ['grouped', 'Composição agrupada']],
      stackMode,
      (e) => { chartPreferences.set(prefKey, e.target.value); rerender(); },
    ));
  }

  const totaisLabels = (pergunta.totais_labels || []).map(cleanText);
  const totaisValues = (pergunta.totais_values || []).map((v) => Number(v || 0));
  const municipioLabels = (pergunta.municipio_labels || []).map(cleanText);
  const rawSeries = Array.isArray(pergunta.municipio_series) ? pergunta.municipio_series : [];

  const sequence = totaisLabels.length ? totaisLabels : rawSeries.map((s) => cleanText(s.label || s.code || ''));
  const seriesMap = new Map(rawSeries.map((s) => [cleanText(s.label || s.code || ''), s]));
  const orderedSeries = sequence.map((l) => seriesMap.get(l)).filter(Boolean);
  rawSeries.forEach((s) => { if (!orderedSeries.includes(s)) orderedSeries.push(s); });

  if (!municipioLabels.length || !orderedSeries.length) {
    body.innerHTML = '<div class="text-muted">Sem dados para esta questão.</div>';
    return;
  }

  const addSection = (text) => {
    const el = document.createElement('div');
    el.className = 'small fw-semibold text-muted mb-2';
    el.textContent = text;
    body.appendChild(el);
  };

  addSection('Número de municípios por opção');
  const totalsCanvas = createCanvas(body, 110);

  body.appendChild(Object.assign(document.createElement('div'), { className: 'my-3' }));

  addSection('Composição por município');
  const munCanvas = createCanvas(body, 130);

  destroyChart(chartInstances, totalsKey);
  destroyChart(chartInstances, munKey);

  // Cada série traz 1/0 por município (mesma ordem de municipioLabels), então dá para
  // derivar quais municípios marcaram cada opção sem pedir nada a mais ao backend.
  const municipiosPorOpcao = orderedSeries.map((s) => {
    const data = Array.isArray(s.data) ? s.data : [];
    return municipioLabels.filter((_, idx) => Number(data[idx] || 0) > 0);
  });

  chartInstances.set(totalsKey, new Chart(totalsCanvas, {
    type: 'bar',
    data: {
      labels: totaisLabels.length ? totaisLabels : orderedSeries.map((s) => cleanText(s.label || s.code || '')),
      datasets: [{
        label: 'Municípios',
        data: totaisValues.length ? totaisValues : orderedSeries.map((s) => (Array.isArray(s.data) ? s.data.reduce((a, c) => a + Number(c || 0), 0) : 0)),
        backgroundColor: (totaisLabels.length ? totaisLabels : orderedSeries).map((_, i) => bg(i)),
      }],
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: (ctx) => `${ctx.parsed.y} município(s)`,
            afterLabel: (ctx) => municipiosPorOpcao[ctx.dataIndex] || [],
          },
        },
      },
      scales: {
        x: { ticks: { color: '#64748b', maxRotation: 50, minRotation: 25 } },
        y: { ticks: { color: '#64748b', precision: 0 } },
      },
    },
  }));

  const datasets = orderedSeries.map((s, i) => ({
    label: cleanText(s.label || s.code || `Série ${i + 1}`),
    data: Array.isArray(s.data) ? s.data.map((v) => Number(v || 0)) : [],
    backgroundColor: bg(i),
    borderColor: bg(i),
  }));

  chartInstances.set(munKey, new Chart(munCanvas, {
    type: 'bar',
    data: { labels: municipioLabels, datasets },
    options: {
      responsive: true,
      plugins: { legend: { display: true } },
      scales: {
        x: { stacked: stackMode === 'stacked', ticks: { color: '#64748b', maxRotation: 50, minRotation: 25 } },
        y: { stacked: stackMode === 'stacked', ticks: { color: '#64748b', precision: 0 } },
      },
    },
  }));
}

function renderMunicipioSeries(body, controls, pergunta, chartInstances, chartPreferences, rerender) {
  const chartKey = `${pergunta.id}::municipio-series`;
  const prefKey = `${pergunta.id}::municipio-series-mode`;
  const mode = chartPreferences.get(prefKey) || (pergunta.chart_mode === 'grouped' ? 'grouped' : 'stacked');

  if (controls) {
    controls.appendChild(makeSelect(
      'form-select form-select-sm', '170px',
      [['stacked', 'Composição empilhada'], ['grouped', 'Composição agrupada']],
      mode,
      (e) => { chartPreferences.set(prefKey, e.target.value); rerender(); },
    ));
  }

  const canvas = createCanvas(body);
  destroyChart(chartInstances, chartKey);

  const labels = (pergunta.municipio_labels || []).map(cleanText);
  const datasets = (pergunta.municipio_series || []).map((s, i) => ({
    label: cleanText(s.label || s.code || `Série ${i + 1}`),
    data: Array.isArray(s.data) ? s.data.map((n) => Number(n || 0)) : [],
    backgroundColor: bg(i),
    borderColor: bg(i),
  }));

  chartInstances.set(chartKey, new Chart(canvas, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true,
      plugins: { legend: { display: true } },
      scales: {
        x: { stacked: mode === 'stacked', ticks: { color: '#64748b', maxRotation: 50, minRotation: 25 } },
        y: { stacked: mode === 'stacked', ticks: { color: '#64748b', precision: 0 } },
      },
    },
  }));
}

// ─── Renderers (orquestração) ───────────────────────────────

function renderSimpleQuestionCard(pergunta, titleOverride, ctx) {
  const titulo = cleanText(titleOverride || pergunta.texto);
  const resumo = cleanText(pergunta.resumo || '');
  const { wrapper, body, controls } = buildCardShell(titulo, pergunta.total || 0, resumo);
  const rerender = () => {
    const scrollY = window.scrollY;
    ctx.renderBlocks(ctx.cachedBlocks);
    requestAnimationFrame(() => window.scrollTo({ top: scrollY, behavior: 'instant' }));
  };

  const isGenericType = !['municipio_level', 'municipio_multiselect', 'municipio_series', 'texto'].includes(pergunta.tipo);
  if (isGenericType) {
    const labels = (pergunta.labels || []).map(cleanText);
    const type = resolveChartType(pergunta, labels, ctx.prefs.get(pergunta.id));
    if (type === 'doughnut' || type === 'polarArea') wrapper.className = 'col-12 col-lg-6';
  } else if (pergunta.tipo === 'municipio_level' && levelChartType(pergunta, ctx.prefs) === 'doughnut') {
    wrapper.className = 'col-12 col-lg-6';
  }

  const respostas = Array.isArray(pergunta.respostas) ? pergunta.respostas : [];
  const exemplos = Array.isArray(pergunta.exemplos) ? pergunta.exemplos : [];

  if (pergunta.tipo === 'municipio_level' && Array.isArray(pergunta.grupos_nivel)) {
    renderMunicipioLevel(body, controls, pergunta, ctx.charts, ctx.prefs, rerender);
  } else if (pergunta.tipo === 'municipio_multiselect' && Array.isArray(pergunta.municipio_series)) {
    renderMunicipioMultiselect(body, controls, pergunta, ctx.charts, ctx.prefs, rerender);
  } else if (pergunta.tipo === 'municipio_series' && Array.isArray(pergunta.municipio_series)) {
    renderMunicipioSeries(body, controls, pergunta, ctx.charts, ctx.prefs, rerender);
  } else if (pergunta.tipo === 'texto') {
    renderTextQuestion(body, pergunta, respostas.length ? respostas : exemplos, ctx.modal);
  } else {
    renderGenericChart(body, controls, pergunta, ctx.charts, ctx.prefs, rerender);
  }

  ctx.container.appendChild(wrapper);
}

function renderMatrixBlockCard(block, ctx) {
  const matriz = block.matrix;
  const blockId = `matrix-${block.id}`;
  if (!ctx.matrixState.has(blockId)) {
    ctx.matrixState.set(blockId, { linhaCodigo: '__ALL__', medida: (matriz.medidas || [])[0] || null, chartType: 'bar' });
  }
  const state = ctx.matrixState.get(blockId);

  const titulo = cleanText(block.title || matriz.texto || block.id);
  const { wrapper, body, controls } = buildCardShell(titulo, 0, '');
  body.closest('.card-body').querySelector('.question-count').textContent = 'Questão matriz';

  const linhaSelect = makeSelect('form-select form-select-sm', '220px',
    [['__ALL__', 'Todas as subquestões'], ...(matriz.linhas || []).map((l) => [l.codigo, cleanText(l.label || l.codigo)])],
    state.linhaCodigo || '__ALL__', draw);

  const medidaSelect = makeSelect('form-select form-select-sm', '170px',
    (matriz.medidas || []).map((m) => [m, cleanText(m)]),
    state.medida || ((matriz.medidas || [])[0] || ''), draw);

  const tipoSelect = makeSelect('form-select form-select-sm', '150px',
    [['bar', 'Barras'], ['line', 'Linha']], state.chartType || 'bar', draw);

  controls.appendChild(linhaSelect);
  controls.appendChild(medidaSelect);
  controls.appendChild(tipoSelect);

  const meta = document.createElement('div');
  meta.className = 'text-muted small mb-2';
  body.appendChild(meta);
  const canvas = createCanvas(body);

  function draw() {
    state.linhaCodigo = linhaSelect.value;
    state.medida = medidaSelect.value;
    state.chartType = tipoSelect.value;

    const yearsMap = aggregateYearsMap(matriz, state.linhaCodigo, state.medida);
    const anos = (matriz.anos || []).filter((a) => Object.prototype.hasOwnProperty.call(yearsMap, a));
    const municipios = extractMunicipios(yearsMap, anos);

    destroyChart(ctx.charts, blockId);

    if (!anos.length || !municipios.length) {
      meta.textContent = 'Sem dados para os filtros selecionados.';
      return;
    }

    const linhaLabel = state.linhaCodigo === '__ALL__'
      ? 'Todas as subquestões'
      : cleanText((matriz.linhas || []).find((i) => i.codigo === state.linhaCodigo)?.label || state.linhaCodigo);

    ctx.charts.set(blockId, new Chart(canvas, {
      type: state.chartType,
      data: { labels: municipios.map(cleanText), datasets: buildYearDatasets(yearsMap, anos, municipios) },
      options: {
        responsive: true,
        plugins: { legend: { display: true }, title: { display: true, text: `${linhaLabel} - ${cleanText(state.medida)}` } },
        scales: {
          x: { ticks: { color: '#64748b', maxRotation: 50, minRotation: 25 } },
          y: { ticks: { color: '#64748b', precision: 0 } },
        },
      },
    }));
    meta.textContent = `Campo de município: ${matriz.municipio_field || 'não identificado'} | Municípios exibidos: ${municipios.length}`;
  }

  draw();
  ctx.container.appendChild(wrapper);
}

function renderLegacyCharts(perguntas, ctx) {
  const rerender = () => {
    const scrollY = window.scrollY;
    renderLegacyCharts(ctx.cachedPerguntas, ctx);
    requestAnimationFrame(() => window.scrollTo({ top: scrollY, behavior: 'instant' }));
  };

  perguntas.forEach((pergunta) => {
    const titulo = cleanText(pergunta.texto);
    const resumo = cleanText(pergunta.resumo || '');
    const { wrapper, body, controls } = buildCardShell(titulo, pergunta.total || 0, resumo);

    const respostas = Array.isArray(pergunta.respostas) ? pergunta.respostas : [];
    const exemplos = Array.isArray(pergunta.exemplos) ? pergunta.exemplos : [];

    if (pergunta.tipo === 'texto') {
      renderTextQuestion(body, pergunta, respostas.length ? respostas : exemplos, ctx.modal);
    } else {
      renderGenericChart(body, controls, pergunta, ctx.charts, ctx.prefs, rerender);
    }

    ctx.container.appendChild(wrapper);
  });
}

// ─── BI Matrizes ────────────────────────────────────────────

function renderBiMatrizes(matrizes, ctx) {
  ctx.cachedBiMatrizes = Array.isArray(matrizes) ? matrizes : [];
  const section = document.getElementById('bi-matriz-section');
  const container = document.getElementById('bi-matriz-container');
  if (!section || !container) return;

  if (!ctx.cachedBiMatrizes.length) {
    section.style.display = 'none';
    container.innerHTML = '';
    destroyChart(ctx.charts, '__bi_matriz__');
    return;
  }

  section.style.display = '';
  container.innerHTML = `
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="row g-2 align-items-end mb-3">
          <div class="col-lg-5 col-md-6">
            <label class="form-label text-muted small mb-1">Questão matriz</label>
            <select class="form-select form-select-sm" id="bi-matriz-select"></select>
          </div>
          <div class="col-lg-3 col-md-6">
            <label class="form-label text-muted small mb-1">Subquestão (linha)</label>
            <select class="form-select form-select-sm" id="bi-matriz-linha"></select>
          </div>
          <div class="col-lg-4 col-md-6">
            <label class="form-label text-muted small mb-1">Medida</label>
            <select class="form-select form-select-sm" id="bi-matriz-medida"></select>
          </div>
        </div>
        <div class="text-muted small mb-2" id="bi-matriz-meta"></div>
        <canvas id="bi-matriz-canvas" height="120"></canvas>
      </div>
    </div>
  `;

  const matrizSelect = container.querySelector('#bi-matriz-select');
  const linhaSelect = container.querySelector('#bi-matriz-linha');
  const medidaSelect = container.querySelector('#bi-matriz-medida');
  const canvas = container.querySelector('#bi-matriz-canvas');
  const metaEl = container.querySelector('#bi-matriz-meta');

  ctx.cachedBiMatrizes.forEach((m) => {
    const opt = document.createElement('option');
    opt.value = m.codigo;
    opt.textContent = `${m.codigo} - ${cleanText(m.texto || m.codigo)}`;
    matrizSelect.appendChild(opt);
  });

  const st = ctx.biState;
  st.matrizCodigo = st.matrizCodigo || ctx.cachedBiMatrizes[0].codigo;
  if (!ctx.cachedBiMatrizes.some((m) => m.codigo === st.matrizCodigo)) {
    st.matrizCodigo = ctx.cachedBiMatrizes[0].codigo;
  }
  matrizSelect.value = st.matrizCodigo;

  const matrizAtual = () => ctx.cachedBiMatrizes.find((m) => m.codigo === matrizSelect.value) || null;

  function popularFiltros() {
    const matriz = matrizAtual();
    if (!matriz) return;

    linhaSelect.innerHTML = '<option value="__ALL__">Todos</option>';
    (matriz.linhas || []).forEach((l) => {
      const opt = document.createElement('option');
      opt.value = l.codigo;
      opt.textContent = cleanText(l.label || l.codigo);
      linhaSelect.appendChild(opt);
    });

    medidaSelect.innerHTML = '';
    (matriz.medidas || []).forEach((m) => {
      const opt = document.createElement('option');
      opt.value = m;
      opt.textContent = cleanText(m);
      medidaSelect.appendChild(opt);
    });

    const linhasCods = (matriz.linhas || []).map((l) => l.codigo);
    const medidas = matriz.medidas || [];
    if (st.linhaCodigo !== '__ALL__' && !linhasCods.includes(st.linhaCodigo)) st.linhaCodigo = linhasCods[0] || '__ALL__';
    if (!medidas.includes(st.medida)) st.medida = medidas[0] || null;
    if (!st.linhaCodigo) st.linhaCodigo = '__ALL__';

    linhaSelect.value = st.linhaCodigo;
    if (st.medida) medidaSelect.value = st.medida;
  }

  function renderGrafico() {
    const matriz = matrizAtual();
    if (!matriz || !canvas) return;

    st.matrizCodigo = matriz.codigo;
    st.linhaCodigo = linhaSelect.value;
    st.medida = medidaSelect.value;

    const yearsMap = aggregateYearsMap(matriz, st.linhaCodigo, st.medida);
    const anos = (matriz.anos || []).filter((a) => Object.prototype.hasOwnProperty.call(yearsMap, a));

    destroyChart(ctx.charts, '__bi_matriz__');

    if (!anos.length) {
      metaEl.textContent = 'Sem dados para os filtros selecionados.';
      return;
    }

    const municipios = extractMunicipios(yearsMap, anos);
    const linhaLabel = st.linhaCodigo === '__ALL__'
      ? 'Todas as subquestões'
      : cleanText((matriz.linhas || []).find((i) => i.codigo === st.linhaCodigo)?.label || st.linhaCodigo);

    ctx.charts.set('__bi_matriz__', new Chart(canvas, {
      type: 'bar',
      data: { labels: municipios.map(cleanText), datasets: buildYearDatasets(yearsMap, anos, municipios) },
      options: {
        responsive: true,
        plugins: { legend: { display: true }, title: { display: true, text: `${linhaLabel} - ${cleanText(st.medida)}` } },
        scales: {
          x: { ticks: { color: '#64748b', maxRotation: 50, minRotation: 25 } },
          y: { ticks: { color: '#64748b', precision: 0 } },
        },
      },
    }));
    metaEl.textContent = `Campo de município: ${matriz.municipio_field || 'não identificado'} | Municípios exibidos: ${municipios.length}`;
  }

  matrizSelect.addEventListener('change', () => { popularFiltros(); renderGrafico(); });
  linhaSelect.addEventListener('change', renderGrafico);
  medidaSelect.addEventListener('change', renderGrafico);
  popularFiltros();
  renderGrafico();
}

// ─── Bootstrap (ponto de entrada) ───────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  const root = document.getElementById('avaliacoes-dashboard');
  if (!root) return;

  const endpoint = root.dataset.endpoint;
  // O script da view controla os filtros e a paginacao dos formularios nativos.
  if (new URL(endpoint, window.location.href).searchParams.get('fonte') !== 'limesurvey') return;

  const cardsQuestoes = document.getElementById('cards-questoes');
  const modal = createTextModal();

  const ctx = {
    container: cardsQuestoes,
    charts: new Map(),
    prefs: new Map(),
    matrixState: new Map(),
    modal,
    cachedBlocks: [],
    cachedPerguntas: [],
    cachedBiMatrizes: [],
    biState: { matrizCodigo: null, linhaCodigo: null, medida: null },

    renderBlocks(blocks) {
      const lista = Array.isArray(blocks) ? blocks : [];
      ctx.cachedBlocks = lista;

      const biSection = document.getElementById('bi-matriz-section');
      if (biSection) biSection.style.display = 'none';

      cardsQuestoes.innerHTML = '';
      if (!lista.length) {
        cardsQuestoes.innerHTML = '<div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-muted text-center">Sem respostas para os filtros aplicados.</div></div></div>';
        return;
      }
      lista.forEach((block) => {
        if (block?.kind === 'matrix' && block?.matrix) renderMatrixBlockCard(block, ctx);
        else if (block?.kind === 'simple' && block?.question) renderSimpleQuestionCard(block.question, block.title || block.question?.texto, ctx);
      });
    },
  };

  const filters = {
    template: document.getElementById('f-template'),
    evento: document.getElementById('f-evento'),
    atividade: document.getElementById('f-atividade'),
    de: document.getElementById('f-de'),
    ate: document.getElementById('f-ate'),
  };
  const totalsEls = {
    submissoes: document.querySelector('[data-total="submissoes"]'),
    questoes: document.querySelector('[data-total="questoes"]'),
    eventos: document.querySelector('[data-total="eventos"]'),
    ultima: document.querySelector('[data-total="ultima"]'),
  };

  function buildParams() {
    const params = new URLSearchParams();
    if (filters.template?.value) params.set('template_id', filters.template.value);
    if (filters.evento?.value) params.set('evento_id', filters.evento.value);
    if (filters.atividade?.value) params.set('atividade_id', filters.atividade.value);
    if (filters.de?.value) params.set('de', filters.de.value);
    if (filters.ate?.value) params.set('ate', filters.ate.value);
    return params.toString();
  }

  function setLoading(on) {
    if (on) {
      cardsQuestoes.innerHTML = '<div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-center text-muted">Carregando gráficos...</div></div></div>';
    }
  }

  function renderTotals(totais) {
    totalsEls.submissoes.textContent = fmt.format(totais.submissoes || 0);
    totalsEls.questoes.textContent = fmt.format(totais.questoes || 0);
    totalsEls.eventos.textContent = fmt.format(totais.eventos || 0);
    totalsEls.ultima.textContent = totais.ultima || '-';
  }

  async function loadData() {
    setLoading(true);
    try {
      const url = new URL(endpoint, window.location.href);
      new URLSearchParams(buildParams()).forEach((value, key) => url.searchParams.set(key, value));
      const response = await fetch(url, { headers: { Accept: 'application/json' } });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload?.erro || 'Erro ao carregar dados.');

      if (payload.sem_dados) {
        renderBiMatrizes([], ctx);
        cardsQuestoes.innerHTML = `<div class="col-12"><div class="alert alert-warning border-0 shadow-sm"><strong>Sem dados disponíveis.</strong> ${payload.mensagem}</div></div>`;
        setLoading(false);
        return;
      }

      renderTotals(payload.totais || {});

      if (Array.isArray(payload.question_blocks) && payload.question_blocks.length > 0) {
        ctx.renderBlocks(payload.question_blocks);
      } else {
        renderBiMatrizes(payload.bi_matrizes || [], ctx);
        cardsQuestoes.innerHTML = '';
        ctx.cachedPerguntas = payload.perguntas || [];
        if (!ctx.cachedPerguntas.length) {
          cardsQuestoes.innerHTML = '<div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-muted text-center">Sem respostas para os filtros aplicados.</div></div></div>';
        } else {
          renderLegacyCharts(ctx.cachedPerguntas, ctx);
        }
      }
    } catch (error) {
      const msg = error?.message || 'Erro ao carregar dados.';
      renderBiMatrizes([], ctx);
      cardsQuestoes.innerHTML = `<div class="card border-0 shadow-sm"><div class="card-body text-danger">${msg}</div></div>`;
    }
  }

  document.querySelectorAll('.js-filter').forEach((el) => el.addEventListener('change', loadData));
  document.getElementById('btn-recarregar')?.addEventListener('click', loadData);
  loadData();
});
