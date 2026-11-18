@extends('layouts.app')

@section('content')
<x-ui.page-hero contained
    eyebrow="Relatório anónimo" eyebrow-icon="shield-lock"
    :title="'Avaliação — '.$atividade->descricao"
    class="mb-0">
    <p class="mb-0">
        {{ \Carbon\Carbon::parse($atividade->dia)->format('d/m/Y') }}
        @if($atividade->municipios->isNotEmpty())
            · {{ $atividade->municipios->map(fn($m) => $m->nome_com_estado ?? $m->nome)->join(', ') }}
        @endif
    </p>
    <p class="mb-0">Ação pedagógica: <strong>{{ $atividade->evento->nome ?? '—' }}</strong></p>
    <x-slot:actions>
        @if($submissoes->isNotEmpty())
            <a href="{{ route('atividades.avaliacoes.pdf', $atividade) }}" class="eg-hero-btn">
                <i class="bi bi-file-earmark-pdf-fill me-1"></i> Baixar PDF
            </a>
            <a href="{{ route('atividades.avaliacoes.pdf', ['atividade' => $atividade, 'formato' => 'docx']) }}" class="eg-hero-btn">
                <i class="bi bi-file-earmark-word me-1"></i> Baixar Word
            </a>
        @endif
        <a href="{{ route('eventos.show', $atividade->evento_id) }}" class="eg-hero-btn">
            <i class="bi bi-arrow-left me-1"></i> Voltar à ação pedagógica
        </a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="container py-4">

    <div class="alert alert-info d-flex align-items-center gap-2 mb-4" role="alert">
        <span style="font-size:1.2rem;">🔒</span>
        <div>
            As avaliações exibidas abaixo são <strong>estritamente anónimas</strong>.
            Nenhum dado identificador do participante (nome, e-mail, CPF) é armazenado
            ou apresentado nesta visualização.
        </div>
    </div>

    <div class="eg-stats-row">
        <x-ui.stat-card color="purple" icon="people-fill" label="Submissões" hint="Respostas completas registadas"
            :value="number_format($totais['submissoes'] ?? 0, 0, ',', '.')" />
        <x-ui.stat-card color="blue" icon="list-check" label="Questões com resposta" hint="Com alguma resposta agregada"
            :value="number_format($totais['questoes'] ?? 0, 0, ',', '.')" />
        <x-ui.stat-card color="green" icon="chat-dots-fill" label="Itens de resposta" hint="Total de campos preenchidos"
            :value="number_format($totais['respostas'] ?? 0, 0, ',', '.')" />
        <x-ui.stat-card color="orange" icon="clock-history" label="Última resposta" hint="Data/hora do último envio" small
            :value="$totais['ultima'] ?? '—'" />
    </div>

    @if($submissoes->isEmpty())
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body py-3">
                <div class="fw-semibold mb-1">Formulário aplicado</div>
                <div class="text-muted small">
                    {{ $avaliacao->templateAvaliacao->nome ?? '—' }}
                    &nbsp;·&nbsp;
                    {{ $avaliacao->avaliacaoQuestoes->count() }} questão(ões) no modelo
                </div>
            </div>
        </div>
        <x-ui.empty-state icon="inbox" muted title="Sem avaliações">
            Nenhuma avaliação recebida ainda para este momento.
        </x-ui.empty-state>
    @else
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body py-3">
                <div class="fw-semibold mb-1">Formulário aplicado</div>
                <div class="text-muted small">
                    {{ $avaliacao->templateAvaliacao->nome ?? '—' }}
                    &nbsp;·&nbsp;
                    {{ $avaliacao->avaliacaoQuestoes->count() }} questão(ões) no modelo
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <h2 class="h5 fw-bold mb-0" style="color:#421944;">Distribuição por questão</h2>
            <span class="badge bg-primary-subtle text-primary">Interativo · padrão: barras horizontais</span>
        </div>

        {{-- vstack no JS: cabeçalhos em largura total; cada indicador tem a sua própria .row com 2 colunas --}}
        <div id="avaliacoes-momento-root" class="mb-4">
            <script type="application/json" id="avaliacoes-perguntas-json">@json($perguntas ?? [])</script>
            <div class="vstack gap-4" id="cards-questoes-momento"></div>
        </div>
    @endif

</div>

<div class="modal fade" id="textAnswersModalMomento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <x-ui.modal-header>
                <span class="js-text-modal-title">Respostas</span>
            </x-ui.modal-header>
            <div class="modal-body">
                <div class="text-muted small mb-2 js-text-modal-count"></div>
                <div class="vstack gap-2 js-text-modal-list" style="max-height: 60vh; overflow: auto;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
  /*
   * Duas colunas (md+): col-md-6 = 50%. Com só um cartão na linha (ex.: 1 questão por indicador),
   * o cartão ficava à esquerda e a metade direita parecia “vazia” — não era outra tela nem cache.
   * Órfãos na linha ocupam largura total.
   */
  @media (min-width: 768px) {
    #cards-questoes-momento .row.g-3 > .col-md-6:only-child,
    #cards-questoes-momento .row.g-3 > .col-md-6:nth-child(odd):nth-last-child(1) {
      flex: 0 0 100%;
      max-width: 100%;
    }
  }
  /* Grelha em duas colunas: evita que a coluna flex “empurre” o gráfico para metade vazia */
  #cards-questoes-momento .row > [class*="col-"] {
    min-width: 0;
  }
  #cards-questoes-momento .question-body {
    min-width: 0;
  }
  @media (max-width: 576px) {
    #cards-questoes-momento .question-header {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.5rem;
    }
    #cards-questoes-momento .question-controls {
      width: 100%;
      justify-content: flex-start;
    }
    #cards-questoes-momento .question-controls select {
      width: 100%;
      max-width: none;
    }
  }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@vite(['resources/js/avaliacoes-distribuicao-charts.js'])
@endpush
