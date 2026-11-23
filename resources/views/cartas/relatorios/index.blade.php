@extends('cartas.layouts.app')

@section('title', 'Relatórios - Cartas para Esperançar')

@section('body')
    @include('cartas.shared._styles')

    <main class="cpe-page cpe-reports-page" id="cartas-relatorios">
        @include('cartas.shared._logo')

        <div class="cpe-reports-shell">
            <header class="cpe-reports-header">
                <div>
                    <h1 class="cpe-title">Relatórios</h1>
                    <p class="cpe-reports-description">Acompanhe as trocas de cartas e a participação dos voluntários.</p>
                    <p class="cpe-reports-updated">Atualizado em {{ $geradoEm->format('d/m/Y H:i') }}</p>
                </div>
                <a href="{{ route('cartas.dashboard') }}" class="cpe-button cpe-button--ghost">Voltar para cartas</a>
            </header>

            <section class="cpe-reports-metrics" aria-label="Capacidade de distribuição de cartas">
                @foreach ([
                    ['chave' => 'voluntarios', 'titulo' => 'Voluntários cadastrados', 'descricao' => 'Participantes com perfil de voluntário'],
                    ['chave' => 'capacidade', 'titulo' => 'Capacidade total', 'descricao' => 'Soma dos limites de cartas dos voluntários'],
                    ['chave' => 'atribuidas', 'titulo' => 'Cartas atribuídas', 'descricao' => 'Trocas vinculadas aos voluntários'],
                    ['chave' => 'saldo', 'titulo' => 'Saldo para atribuição', 'descricao' => 'Capacidade total menos cartas atribuídas'],
                ] as $metrica)
                    <article class="cpe-report-card cpe-report-metric">
                        <h2>{{ $metrica['titulo'] }}</h2>
                        <p class="cpe-report-metric-value">{{ number_format($metricas[$metrica['chave']], 0, ',', '.') }}</p>
                        <p>{{ $metrica['descricao'] }}</p>
                    </article>
                @endforeach
            </section>

            <section class="cpe-reports-grid" aria-label="Gráficos de participação">
                @include('cartas.relatorios._grafico', [
                    'id' => 'respostas',
                    'titulo' => 'Cartas com resposta',
                    'descricao' => 'Trocas iniciadas pelos educandos que já receberam ao menos uma resposta do voluntário.',
                    'grafico' => $graficos['respostas'],
                    'unidade' => 'trocas de cartas',
                ])
                @include('cartas.relatorios._grafico', [
                    'id' => 'vinculos',
                    'titulo' => 'Tipos de voluntários',
                    'descricao' => 'Distribuição dos voluntários pelo vínculo informado no cadastro.',
                    'grafico' => $graficos['vinculos'],
                    'unidade' => 'voluntários',
                ])
            </section>

            <section class="cpe-reports-grid" aria-label="Listagens de voluntários">
                @include('cartas.relatorios._voluntarios', [
                    'id' => 'voluntarios-pendentes',
                    'titulo' => 'Voluntários com cartas não respondidas',
                    'descricao' => 'Cartas que ainda aguardam a primeira resposta do voluntário.',
                    'voluntarios' => $pendentes,
                    'mostrarPendencias' => true,
                    'mensagemVazia' => 'Nenhum voluntário com cartas aguardando a primeira resposta.',
                ])
                @include('cartas.relatorios._voluntarios', [
                    'id' => 'voluntarios-sem-cartas',
                    'titulo' => 'Voluntários sem cartas atribuídas',
                    'descricao' => 'Voluntários que ainda não possuem trocas de cartas atribuídas.',
                    'voluntarios' => $semCartas,
                    'mostrarPendencias' => false,
                    'mensagemVazia' => 'Nenhum voluntário sem cartas atribuídas.',
                ])
            </section>
        </div>

        @include('cartas.shared._user-menu')
    </main>

    <script type="application/json" id="cartas-relatorios-dados">{!! \Illuminate\Support\Js::encode($graficos) !!}</script>
    @include('cartas.shared._scripts')
@endsection

@push('scripts')
    @vite('resources/js/cartas/relatorios.js')
@endpush
