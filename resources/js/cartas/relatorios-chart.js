const integer = new Intl.NumberFormat('pt-BR');
const percentage = new Intl.NumberFormat('pt-BR', { style: 'percent', maximumFractionDigits: 1 });

export function formatPercentage(value, total) {
    return percentage.format(total > 0 ? value / total : 0);
}

export function createChartConfig(data, selectedType, colors) {
    const type = selectedType === 'auto' ? 'doughnut' : selectedType;
    const horizontal = type === 'bar-horizontal';
    const baseType = horizontal ? 'bar' : type;
    const circular = ['doughnut', 'polarArea'].includes(baseType);
    const total = data.values.reduce((sum, value) => sum + value, 0);

    const options = {
        responsive: true,
        maintainAspectRatio: false,
        font: { family: "'Montserrat', system-ui, sans-serif" },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (context) => integer.format(context.raw) + ' (' + formatPercentage(context.raw, total) + ')',
                },
            },
        },
    };

    if (horizontal) options.indexAxis = 'y';

    if (!circular) {
        const countAxis = horizontal ? 'x' : 'y';
        options.scales = {
            x: { ticks: { color: '#64748b' } },
            y: { ticks: { color: '#64748b' } },
        };
        options.scales[countAxis].beginAtZero = true;
        options.scales[countAxis].ticks.precision = 0;
    }

    return {
        type: baseType,
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Quantidade',
                data: data.values,
                backgroundColor: baseType === 'line' ? colors[0] + '20' : colors,
                borderColor: circular ? '#ffffff' : colors[0],
                borderWidth: circular ? 2 : 1,
                hoverOffset: circular ? 8 : 0,
                tension: 0.2,
                fill: baseType === 'line',
            }],
        },
        options,
    };
}
