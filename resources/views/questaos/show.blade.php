@extends('layouts.app')

@section('content')
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="question-circle" title="Questão">
    <x-slot:actions>
        <a href="{{ route('questaos.edit', $questao) }}" class="eg-hero-btn">Editar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h2 class="h5 fw-semibold">Enunciado</h2>
        <p class="mb-4">{{ $questao->texto }}</p>

        <div class="row mb-3">
          <div class="col-md-4">
            <span class="fw-semibold d-block text-muted text-uppercase small">Tipo</span>
            <span>{{ ucfirst($questao->tipo) }}</span>
          </div>
          <div class="col-md-4">
            <span class="fw-semibold d-block text-muted text-uppercase small">Indicador</span>
            <span>{{ $questao->indicador->descricao ?? '—' }}</span>
          </div>
          <div class="col-md-4">
            <span class="fw-semibold d-block text-muted text-uppercase small">Dimensão</span>
            <span>{{ $questao->indicador->dimensao->descricao ?? '—' }}</span>
          </div>
        </div>

        <div class="mb-3">
          <span class="fw-semibold d-block text-muted text-uppercase small">Escala</span>
          @if ($questao->escala)
          <p class="mb-1">{{ $questao->escala->descricao }}</p>
          @php
            $opcoes = collect([$questao->escala->opcao1, $questao->escala->opcao2, $questao->escala->opcao3, $questao->escala->opcao4, $questao->escala->opcao5])->filter();
          @endphp
          <div class="vstack gap-2">
            @foreach ($opcoes as $opcao)
            <div class="border rounded p-2 bg-light">{!! $opcao !!}</div>
            @endforeach
          </div>
          @else
          <span class="text-muted">-</span>
          @endif
        </div>

        <div class="mb-3">
          <span class="fw-semibold d-block text-muted text-uppercase small">Questão fixa?</span>
          <span>{{ $questao->fixa ? 'Sim' : 'Não' }}</span>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 fw-semibold text-uppercase text-muted mb-3">Modelo de avaliação associado</h2>
        @if ($questao->template)
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <p class="fw-semibold mb-0">{{ $questao->template->nome }}</p>
            <small class="text-muted">{{ $questao->template->descricao ?: 'Sem descrição' }}</small>
          </div>
          <a href="{{ route('templates-avaliacao.show', $questao->template) }}" class="btn btn-sm btn-outline-primary">Ver modelo</a>
        </div>
        @else
        <p class="text-muted mb-0">Nenhum modelo associado.</p>
        @endif
      </div>
    </div>

    <a href="{{ route('questaos.index') }}" class="btn btn-link px-0 mt-3">Voltar para lista</a>
  </div>
</div>
@endsection
