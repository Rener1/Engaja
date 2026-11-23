import Chart from 'chart.js/auto';
import '../../css/cartas-relatorios.css';
import { createChartConfig, formatPercentage } from './relatorios-chart';

const number = new Intl.NumberFormat('pt-BR');

function renderLegend(legend, data, colors, chart) {
    const items = data.labels.map((label, index) => {
        const value = data.values[index];
        const item = document.createElement('li');
        item.classList.toggle('is-empty', value === 0);

        const dot = document.createElement('span');
        dot.className = 'dot';
        dot.style.backgroundColor = colors[index];
        dot.setAttribute('aria-hidden', 'true');

        const text = document.createElement('span');
        text.className = 'lbl';
        text.textContent = label;

        const count = document.createElement('strong');
        count.className = 'val';
        count.textContent = number.format(value);

        const percent = document.createElement('em');
        percent.textContent = formatPercentage(value, data.total);

        item.append(dot, text, count, percent);
        item.addEventListener('mouseenter', () => {
            chart.setActiveElements([{ datasetIndex: 0, index }]);
            chart.update('none');
        });
        item.addEventListener('mouseleave', () => {
            chart.setActiveElements([]);
            chart.update('none');
        });
        return item;
    });

    legend.replaceChildren(...items);
}

function initializeChart(card, data, palette) {
    if (!data || data.total === 0) return;

    const canvas = card.querySelector('[data-chart-canvas]');
    const select = card.querySelector('[data-chart-type]');
    const layout = card.querySelector('[data-chart-layout]');
    const legend = card.querySelector('[data-chart-legend]');
    const error = card.querySelector('[data-chart-error]');
    const colors = data.labels.map((_, index) => palette[index % palette.length]);
    let chart = null;

    function render() {
        chart?.destroy();
        chart = null;
        error.hidden = true;
        canvas.hidden = false;

        try {
            const config = createChartConfig(data, select.value, colors);
            layout.classList.toggle('is-circular', ['doughnut', 'polarArea'].includes(config.type));
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                config.options.animation = false;
            }
            chart = new Chart(canvas, config);
            renderLegend(legend, data, colors, chart);
        } catch (exception) {
            chart?.destroy();
            chart = null;
            canvas.hidden = true;
            error.hidden = false;
            console.error('Falha ao exibir o gráfico do relatório de cartas.', exception);
        }
    }

    select.addEventListener('change', render);
    render();
}

function boot() {
    const root = document.getElementById('cartas-relatorios');
    if (!root || root.dataset.initialized === 'true') return;
    root.dataset.initialized = 'true';

    try {
        const data = JSON.parse(document.getElementById('cartas-relatorios-dados').textContent);
        const theme = getComputedStyle(root);
        const palette = [
            theme.getPropertyValue('--cpe-purple').trim() || '#a900d9',
            theme.getPropertyValue('--cpe-blue').trim() || '#008fbd',
            '#94a3b8',
        ];
        root.querySelectorAll('[data-cartas-chart]').forEach((card) => {
            initializeChart(card, data[card.dataset.cartasChart], palette);
        });
    } catch (exception) {
        root.querySelectorAll('[data-chart-error]').forEach((error) => { error.hidden = false; });
        console.error('Falha ao carregar os gráficos dos relatórios de cartas.', exception);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
