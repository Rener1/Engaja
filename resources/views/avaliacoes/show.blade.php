@extends('layouts.app')

@section('content')
@php
  $isUniversal = $avaliacao->atividade_id === null;
  $isTranscricao = $avaliacao->transcricao;
  $editUrl = $isUniversal
    ? route('avaliacoes-universais.edit', $avaliacao)
    : ($isTranscricao ? route('avaliacoes-transcricoes.edit', $avaliacao) : route('avaliacoes.edit', $avaliacao));
  $backUrl = $isUniversal
    ? route('avaliacoes-universais.index')
    : ($isTranscricao ? route('avaliacoes-transcricoes.index') : route('avaliacoes.index'));
@endphp
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="clipboard2-check" title="Avaliação">
    <p class="text-muted mb-0">Registrada em {{ $avaliacao->created_at?->format('d/m/Y H:i') ?? '-' }}</p>
    <x-slot:actions>
        @if(!$avaliacao->anonima || $isTranscricao)
          <a href="{{ route('avaliacoes.respostas', $avaliacao) }}" class="eg-hero-btn">Ver respostas</a>
        @endif
        <a href="{{ $editUrl }}" class="eg-hero-btn">Editar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-12">
    @php
      $inscricaoExibida = $avaliacao->inscricao ?? $avaliacao->respostas->first()?->inscricao;
      $participanteNome = $inscricaoExibida?->participante?->user?->name;
      $eventoNome = $inscricaoExibida?->evento?->nome;
    @endphp

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h2 class="h6 fw-semibold text-uppercase text-muted mb-3">Contexto</h2>
        <dl class="row mb-0">
          <dt class="col-md-4 text-muted">Participante</dt>
          <dd class="col-md-8">{{ $participanteNome ?? '-' }}</dd>

          <dt class="col-md-4 text-muted">Evento</dt>
          <dd class="col-md-8">{{ $eventoNome ?? '-' }}</dd>

          <dt class="col-md-4 text-muted">Atividade</dt>
          <dd class="col-md-8">
            @if($isUniversal)
              Avaliação universal
            @elseif($isTranscricao)
              {{ $avaliacao->atividade->descricao ?? '-' }}
            @else
              {{ $avaliacao->atividade->descricao ?? '-' }}
            @endif
          </dd>

          <dt class="col-md-4 text-muted">Modelo de avaliação</dt>
          <dd class="col-md-8">{{ $avaliacao->templateAvaliacao->nome ?? '-' }}</dd>

          <dt class="col-md-4 text-muted">Descrição</dt>
          <dd class="col-md-8">{{ $avaliacao->descricao_universal ?: '-' }}</dd>
        </dl>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 fw-semibold text-uppercase text-muted mb-3">Questões e respostas</h2>

        @php
          $respostas = $avaliacao->respostas->pluck('resposta', 'avaliacao_questao_id');
        @endphp

        <ol class="list-group list-group-numbered list-group-flush">
          @forelse ($avaliacao->avaliacaoQuestoes as $questao)
            <li class="list-group-item px-0">
              <p class="fw-semibold mb-1">{{ $questao->texto }}</p>
              <p class="text-muted small mb-1">
                Indicador: {{ $questao->indicador->descricao ?? '-' }}
                @if ($questao->indicador && $questao->indicador->dimensao)
                  &bull; Dimensão: {{ $questao->indicador->dimensao->descricao ?? '-' }}
                @endif
              </p>
              <p class="text-muted small mb-2">
                Tipo: {{ $tiposQuestao[$questao->tipo] ?? ucfirst($questao->tipo) }}
                @if ($questao->evidencia)
                  &bull; Evidência: {{ $questao->evidencia->descricao }}
                @endif
                @if ($questao->tipo === 'escala' && $questao->escala)
                  &bull; Escala: {{ $questao->escala->descricao }}
                @endif
              </p>
              <p class="mb-0">
                @php $resposta = $respostas[$questao->id] ?? null; @endphp
                {{ $resposta !== null && $resposta !== '' ? $resposta : 'Sem resposta' }}
              </p>
            </li>
          @empty
            <li class="list-group-item px-0 text-muted">Nenhuma questão cadastrada.</li>
          @endforelse
        </ol>
      </div>
    </div>

    <a href="{{ $backUrl }}" class="btn btn-link px-0 mt-3">Voltar para lista</a>
  </div>
</div>
@endsection
