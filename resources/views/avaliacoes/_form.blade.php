@extends('layouts.app')

@section('content')
@php
  $isUniversal = $isUniversal ?? false;
  $isTranscricao = ($isTranscricao ?? false) || request('transcricao');
  $formAction = $formAction ?? route('avaliacao.formulario.responder', $avaliacao);
  $tituloAvaliacao = $isUniversal
      ? ($avaliacao->descricao_universal ?: ($avaliacao->templateAvaliacao->nome ?? 'Avaliação universal'))
      : ($atividade?->descricao ?? $avaliacao->templateAvaliacao->nome ?? 'Avaliação');
@endphp
<div class="row justify-content-center">
  <div class="col-xl-8">
    <div class="text-center mb-4">
      <h1 class="h3 fw-bold text-engaja mb-1">
        Avaliação - {{ $tituloAvaliacao }}
      </h1>
      <p class="text-muted mb-0" style="text-align: justify">
        Convidamos você a responder esta avaliação, expressando as suas opiniões, críticas e sugestões.
        Esta coleta de dados segue as diretrizes da LGPD. Suas respostas contribuirão para o aprimoramento do nosso trabalho.
      </p>
    </div>

    @if($errors->any())
      <div class="alert alert-danger">
        <strong>Ops!</strong> Verifique os campos destacados e tente novamente.
      </div>
    @endif

    <div class="card shadow-sm">
      <div class="card-body">
        @php
          $inscricaoExibida = $inscricaoRespondente ?? $avaliacao->inscricao ?? $avaliacao->respostas->first()?->inscricao;
          $eventoNome = $inscricaoExibida?->evento?->nome ?? $atividade?->evento?->nome;
          $respostas = $respostasExistentes ?? collect();
          $formBloqueado = $jaRespondeu ?? false;
          $formularioFechado = $formularioFechado ?? false;
          $somenteVisualizacao = $somenteVisualizacao ?? false;
          $exigePresenca = ! $isUniversal && ! $isTranscricao;
        @endphp

        <div class="mb-4">
          @if($isUniversal)
            <p class="mb-0"><strong>Avaliação universal:</strong> {{ $avaliacao->descricao_universal ?: ($avaliacao->templateAvaliacao->nome ?? '-') }}</p>
          @elseif($isTranscricao)
            <p class="mb-0"><strong>Transcrição:</strong> {{ $avaliacao->descricao_universal ?: ($avaliacao->templateAvaliacao->nome ?? '-') }}</p>
            <p class="mb-0"><strong>Momento:</strong> {{ $atividade?->descricao ?? '-' }}</p>
          @else
            <p class="mb-0"><strong>Ação pedagógica:</strong> {{ $eventoNome ?? '-' }}</p>
            @if($avaliacao->descricao_universal)
              <p class="mb-0"><strong>Avaliação:</strong> {{ $avaliacao->descricao_universal }}</p>
            @endif
          @endif
        </div>

        @if($exigePresenca && empty($token))
          <div class="alert alert-warning">Confirme sua presença no momento para gerar o link pessoal desta avaliação.</div>
        @endif

        @if($formBloqueado)
          <div class="alert alert-info">
            {{ $formularioFechado ? 'Este formulário não está recebendo respostas no momento.' : 'Você já respondeu este formulário. Obrigado pelo retorno!' }}
          </div>
        @endif

        @if($somenteVisualizacao)
          <div class="alert alert-info">Pré-visualização do formulário. As respostas não podem ser enviadas nesta tela.</div>
        @endif

        <form method="POST" action="{{ $formAction }}">
          @csrf
          <input type="hidden" name="token" value="{{ old('token', $token) }}">
          @if(request('transcricao') || ($isTranscricao ?? false))
            <input type="hidden" name="transcricao" value="1">
          @endif

          <fieldset @disabled($formBloqueado || $somenteVisualizacao)>
            @php
              $questoesAgrupadas = $avaliacao->avaliacaoQuestoes
                ->sortBy(function ($q) {
                  $dim = mb_strtolower($q->indicador->dimensao->descricao ?? '');
                  $ind = mb_strtolower($q->indicador->descricao ?? '');
                  $ordem = $q->ordem ?? 999;
                  return sprintf('%s|%s|%03d|%06d', $dim, $ind, $ordem, $q->id);
                })
                ->groupBy(fn($q) => $q->indicador->dimensao->descricao ?? 'Sem dimensão')
                ->map(fn($colecao) => $colecao->groupBy(fn($q) => $q->indicador->descricao ?? 'Sem indicador'));
              $contador = 0;
            @endphp

            <div class="list-group list-group-flush">
              @forelse($questoesAgrupadas as $dimensao => $indicadores)
                <div class="list-group-item px-0">
                  <h5 class="fw-bold text-engaja mb-2">Dimensão — {{ $dimensao }}</h5>

                  @foreach($indicadores as $indicador => $questoes)
                    <div class="mb-2">
                      <p class="fw-semibold mb-2" style="color: #008BBC;">Indicador — {{ $indicador }}</p>
                      <ol class="list-unstyled mb-3">
                        @foreach($questoes as $questao)
                          @php
                            $contador++;
                            $valorAtual = old("respostas.{$questao->id}", $respostas[$questao->id] ?? null);
                          @endphp
                          <li class="mb-3">
                            <p class="fw-semibold mb-1">
                              <span class="text-muted me-2">{{ $contador }}.</span>
                              {{ $questao->texto }}
                            </p>

                            <div class="mt-2">
                              @switch($questao->tipo)
                                @case('numero')
                                  <input type="number" step="any" name="respostas[{{ $questao->id }}]" class="form-control"
                                    value="{{ $valorAtual }}" placeholder="Digite um número">
                                  @break

                                @case('escala')
                                  @php $opcoesEscala = collect($questao->escala?->valores ?? []); @endphp
                                  @if($opcoesEscala->isNotEmpty())
                                    <div class="d-flex flex-column gap-2">
                                      @foreach($opcoesEscala as $indice => $opcao)
                                        @php $inputId = 'q'.$questao->id.'_'.($indice + 1); @endphp
                                        <div class="form-check">
                                          <input class="form-check-input" type="radio"
                                            name="respostas[{{ $questao->id }}]"
                                            id="{{ $inputId }}"
                                            value="{{ $opcao }}"
                                            {{ (string) $valorAtual === (string) $opcao ? 'checked' : '' }}>
                                          <label class="form-check-label" for="{{ $inputId }}">{{ strip_tags($opcao) }}</label>
                                        </div>
                                      @endforeach
                                    </div>
                                  @else
                                    <p class="text-muted small">Escala não configurada.</p>
                                  @endif
                                  @break

                                @case('boolean')
                                  <div class="d-flex flex-column gap-2">
                                    @php $inputSim = 'q'.$questao->id.'_sim'; $inputNao = 'q'.$questao->id.'_nao'; @endphp
                                    <div class="form-check">
                                      <input class="form-check-input" type="radio" name="respostas[{{ $questao->id }}]" value="1" id="{{ $inputSim }}"
                                        {{ (string) $valorAtual === '1' ? 'checked' : '' }}>
                                      <label class="form-check-label" for="{{ $inputSim }}">Sim</label>
                                    </div>
                                    <div class="form-check">
                                      <input class="form-check-input" type="radio" name="respostas[{{ $questao->id }}]" value="0" id="{{ $inputNao }}"
                                        {{ (string) $valorAtual === '0' ? 'checked' : '' }}>
                                      <label class="form-check-label" for="{{ $inputNao }}">Não</label>
                                    </div>
                                  </div>
                                  @break

                                @case('unica')
                                  @php $opcoesResposta = collect($questao->opcoes_resposta ?? []); @endphp
                                  @if($opcoesResposta->isNotEmpty())
                                    <select name="respostas[{{ $questao->id }}]" class="form-select">
                                      <option value="">Selecione...</option>
                                      @foreach($opcoesResposta as $opcao)
                                        <option value="{{ $opcao }}" @selected((string) $valorAtual === (string) $opcao)>{{ $opcao }}</option>
                                      @endforeach
                                    </select>
                                  @else
                                    <p class="text-muted small">Opções não configuradas.</p>
                                  @endif
                                  @break

                                @case('multipla')
                                    @php
                                        //transforma o valor num array válido
                                        $respostasSelecionadas = is_array($valorAtual)
                                            ? $valorAtual
                                            : (json_decode($valorAtual, true) ?? []);
                                    @endphp
                                    <div class="d-flex flex-column gap-2 mt-2">
                                        @foreach($questao->opcoes_resposta as $index => $opcao)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="respostas[{{ $questao->id }}][]"
                                                       value="{{ $opcao }}"
                                                       id="q_{{ $questao->id }}_op_{{ $index }}"
                                                    {{ in_array($opcao, $respostasSelecionadas) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="q_{{ $questao->id }}_op_{{ $index }}">
                                                    {{ $opcao }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @break

                                @default
                                  <textarea name="respostas[{{ $questao->id }}]" class="form-control" rows="3" placeholder="Compartilhe sua percepção">{{ $valorAtual }}</textarea>
                              @endswitch
                            </div>
                            @error("respostas.{$questao->id}")
                              <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                          </li>
                        @endforeach
                      </ol>
                    </div>
                  @endforeach
                </div>
              @empty
                <div class="list-group-item px-0 text-muted">Nenhuma questão cadastrada para esta avaliação.</div>
              @endforelse
            </div>
          </fieldset>

          @unless($formBloqueado || $somenteVisualizacao)
          <div class="text-end mt-4">
            <button type="submit" class="btn btn-primary">
              Enviar avaliação
            </button>
          </div>
          @endunless
        </form>

      </div>
    </div>
  </div>
</div>
@endsection
