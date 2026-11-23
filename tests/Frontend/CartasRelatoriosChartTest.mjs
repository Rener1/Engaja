import test from 'node:test';
import assert from 'node:assert/strict';
import { createChartConfig, formatPercentage } from '../../resources/js/cartas/relatorios-chart.js';

const data = { labels: ['Com resposta', 'Sem resposta'], values: [3, 1], total: 4 };
const colors = ['#a900d9', '#008fbd'];

for (const [selected, expected] of [
    ['auto', 'doughnut'],
    ['bar', 'bar'],
    ['bar-horizontal', 'bar'],
    ['doughnut', 'doughnut'],
    ['polarArea', 'polarArea'],
    ['line', 'line'],
]) {
    test('preserva os dados ao selecionar ' + selected, () => {
        const config = createChartConfig(data, selected, colors);
        assert.equal(config.type, expected);
        assert.deepEqual(config.data.labels, data.labels);
        assert.deepEqual(config.data.datasets[0].data, [3, 1]);
        assert.equal(config.options.maintainAspectRatio, false);
        assert.equal(config.options.plugins.legend.display, false);
    });
}

test('barras horizontais usam o eixo de quantidades correto', () => {
    const config = createChartConfig(data, 'bar-horizontal', colors);
    assert.equal(config.options.indexAxis, 'y');
    assert.equal(config.options.scales.x.beginAtZero, true);
    assert.equal(config.options.scales.x.ticks.precision, 0);
});

test('graficos circulares nao recebem eixos cartesianos', () => {
    for (const type of ['doughnut', 'polarArea']) {
        assert.equal(createChartConfig(data, type, colors).options.scales, undefined);
    }
});

test('tooltip informa quantidade e percentual da mesma base', () => {
    const config = createChartConfig(data, 'bar', colors);
    assert.equal(config.options.plugins.tooltip.callbacks.label({ raw: 3 }), '3 (75%)');
    assert.equal(formatPercentage(0, 0), '0%');
    assert.equal(formatPercentage(1, 4), '25%');
});
