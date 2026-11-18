@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="files" :title="$template->nome">
    <p class="text-muted mb-0">{{ $template->descricao ?: 'Sem descrição' }}</p>
    <x-slot:actions>
        <a href="{{ route('templates-avaliacao.edit', $template) }}" class="eg-hero-btn">Editar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 fw-semibold text-uppercase text-muted mb-3">Questões</h2>

        <ol class="list-group list-group-numbered list-group-flush">
          @forelse ($template->questoes as $questao)
          <li class="list-group-item px-0">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <p class="fw-semibold mb-1">{{ $questao->texto }}</p>
                <p class="text-muted small mb-1">
                  Indicador: {{ $questao->indicador->descricao ?? '—' }} |
                  Dimensão: {{ $questao->indicador->dimensao->descricao ?? '—' }}
                </p>
                <p class="text-muted small mb-0">
                  Tipo: {{ ucfirst($questao->tipo) }}
                  @if ($questao->escala)
                  • Escala: {{ $questao->escala->descricao }}
                  @endif
                </p>
              </div>
              <span class="badge bg-light text-dark border">Ordem {{ $questao->ordem ?? '—' }}</span>
            </div>
          </li>
          @empty
          <li class="list-group-item px-0 text-muted">Nenhuma questão vinculada.</li>
          @endforelse
        </ol>
      </div>
    </div>

    <a href="{{ route('templates-avaliacao.index') }}" class="btn btn-link px-0 mt-3">Voltar para lista</a>
  </div>
</div>
@endsection
