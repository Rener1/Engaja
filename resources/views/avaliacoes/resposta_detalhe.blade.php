@extends('layouts.app')

@section('content')
@php
  $isUniversal = $avaliacao->atividade_id === null;
  $isTranscricao = $avaliacao->transcricao;
    $respondente = ($isUniversal || $isTranscricao)
      ? 'resposta anonima'
      : ($submissao->presenca?->inscricao?->participante?->user?->name ?? 'N/A');
@endphp
<x-ui.page-hero eyebrow="Avaliações" eyebrow-icon="clipboard2-check" :title="'Respostas de '.$respondente">
    <x-slot:actions>
        <a href="{{ route('avaliacoes.respostas', $avaliacao) }}" class="eg-hero-btn">Voltar</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="row justify-content-center">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">
        <p class="mb-3">
          <strong>Enviado em:</strong> {{ $submissao->created_at->format('d/m/Y H:i') }}<br>
          @if($isUniversal)
          <strong>Avaliação universal:</strong> {{ $avaliacao->descricao_universal ?: 'Sem descrição' }}
          @elseif($isTranscricao)
          <strong>Transcrição:</strong> {{ $avaliacao->descricao_universal ?: 'Sem descrição' }}<br>
          <strong>Momento:</strong> {{ $avaliacao->atividade->descricao ?? 'N/A' }} — {{ $avaliacao->atividade->evento->nome ?? 'N/A' }}
          @else
          <strong>Atividade:</strong> {{ $avaliacao->atividade->descricao ?? 'N/A' }} — {{ $avaliacao->atividade->evento->nome ?? 'N/A' }}
          @endif
        </p>

        <div class="list-group">
          @foreach($avaliacao->avaliacaoQuestoes as $questao)
            @php
              $resp = $respostasPorQuestao->get($questao->id);
            @endphp
            <div class="list-group-item">
              <div class="fw-semibold mb-1">{{ $questao->texto }}</div>
              <div class="text-muted small mb-2">
                Tipo: {{ $questao->tipo }} |
                Evidência: {{ $questao->evidencia->descricao ?? '—' }} |
                Escala: {{ $questao->escala->descricao ?? '—' }}
              </div>
                <div>
                    @if($resp)
                        @php
                          $exibicao = $resp->resposta;
                          if ($questao->tipo === 'boolean') {
                            $normalizado = is_bool($exibicao) ? ($exibicao ? '1' : '0') : (string) $exibicao;
                            if ($normalizado === '1' || strtolower($normalizado) === 'true') {
                              $exibicao = 'Sim';
                            } elseif ($normalizado === '0' || strtolower($normalizado) === 'false') {
                              $exibicao = 'Não';
                            }
                          } elseif (is_string($exibicao) && in_array(substr(trim($exibicao), 0, 1), ['[', '{'])) {
                            $decoded = json_decode($exibicao, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                              $exibicao = implode(', ', $decoded);
                            }
                          }
                        @endphp
                        {!! nl2br(e($exibicao)) !!}
                    @else
                        <span class="text-muted">Não respondida.</span>
                    @endif
                </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
