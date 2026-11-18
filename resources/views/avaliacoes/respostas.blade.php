@extends('layouts.app')

@section('content')
@php
  $isUniversal = $avaliacao->atividade_id === null;
  $isTranscricao = $avaliacao->transcricao;
  $isSemPresenca = $isUniversal || $isTranscricao;
  $backUrl = $isUniversal
    ? route('avaliacoes-universais.index')
    : ($isTranscricao ? route('avaliacoes-transcricoes.index') : route('avaliacoes.show', $avaliacao));
@endphp
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="clipboard2-check" title="Respostas da avaliação">
    <x-slot:actions>
        <a href="{{ $backUrl }}" class="eg-hero-btn">Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <p class="mb-3">
          @if($isUniversal)
          <strong>Avaliação universal:</strong> {{ $avaliacao->descricao_universal ?: 'Sem descrição' }}<br>
          @elseif($isTranscricao)
          <strong>Transcrição:</strong> {{ $avaliacao->descricao_universal ?: 'Sem descrição' }}<br>
          <strong>Momento:</strong> {{ $avaliacao->atividade->descricao ?? 'N/A' }} —
          <strong>Ação pedagógica:</strong> {{ $avaliacao->atividade->evento->nome ?? 'N/A' }}<br>
          @else
          <strong>Atividade:</strong> {{ $avaliacao->atividade->descricao ?? 'N/A' }} —
          <strong>Ação pedagógica:</strong> {{ $avaliacao->atividade->evento->nome ?? 'N/A' }}<br>
          @endif
          <strong>Modelo:</strong> {{ $avaliacao->templateAvaliacao->nome ?? 'N/A' }}
        </p>

        @if($submissoes->isEmpty())
          <x-ui.empty-state icon="inbox" muted>Nenhuma resposta enviada ainda.</x-ui.empty-state>
        @else
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  @unless($isSemPresenca)
                  <th>Participante</th>
                  <th>Email</th>
                  @endunless
                  <th>Enviado em</th>
                  <th class="text-end">Ações</th>
                </tr>
              </thead>
              <tbody>
                @foreach($submissoes as $submissao)
                  @php
                    $user = $isSemPresenca ? null : ($submissao->presenca->inscricao->participante->user ?? null);
                  @endphp
                  <tr>
                    @unless($isSemPresenca)
                    <td>{{ $user?->name ?? 'N/A' }}</td>
                    <td>{{ $user?->email ?? 'N/A' }}</td>
                    @endunless
                    <td>{{ $submissao->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary"
                        href="{{ route('avaliacoes.respostas.mostrar', [$avaliacao, $submissao]) }}">
                        Ver respostas
                      </a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
