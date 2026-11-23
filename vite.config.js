import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/js/dashboards/avaliacoes.js',
                'resources/js/avaliacoes-distribuicao-charts.js',
                'resources/js/cartas/relatorios.js',
            ],
            refresh: true,
        }),
    ],
});
