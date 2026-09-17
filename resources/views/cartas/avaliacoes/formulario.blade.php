@extends('cartas.layouts.app')

@section('title', 'Avaliação - Cartas para Esperançar')

@section('body')
  @include('cartas.shared._styles')

  <main class="cpe-page">
    <div class="cpe-logo-top">
      <a href="{{ $homeUrl ?? route('cartas.apresentacao') }}" aria-label="Voltar ao início do Cartas">
        <img src="{{ asset('images/cartas/cartas-logo.png') }}" alt="Cartas para Esperançar" class="cpe-logo">
      </a>
    </div>

    @php
      $isUniversal = $isUniversal ?? false;
      $isTranscricao = ($isTranscricao ?? false) || request('transcricao');
      $formAction = $formAction ?? route('avaliacao.formulario.responder', $avaliacao);
      $tituloAvaliacao = $isUniversal
          ? ($avaliacao->descricao_universal ?: ($avaliacao->templateAvaliacao->nome ?? 'Avaliação universal'))
          : ($atividade?->descricao ?? $avaliacao->templateAvaliacao->nome ?? 'Avaliação');
    @endphp

    <section style="max-width: 820px; margin: 0 auto; padding: 0 24px 48px;">

      <div style="text-align: center; margin-bottom: 28px; margin-top: 28px">
        <h1 class="cpe-title" style="font-size: 28px; margin-bottom: 8px;">
          Avaliação — {{ $tituloAvaliacao }}
        </h1>
      </div>

      @if($errors->any())
        <div class="cpe-alert cpe-alert--error" style="text-align: left; border: 1px solid #d04b4b; border-radius: 6px; padding: 10px 14px; margin-bottom: 18px;">
          <strong>Ops!</strong> Verifique os campos destacados e tente novamente.
        </div>
      @endif

      <div style="background: #fff; border-radius: 10px; box-shadow: 0 2px 14px rgba(0,0,0,.06); padding: 28px 24px;">
        @php
          $inscricaoExibida = $inscricaoRespondente ?? $avaliacao->inscricao ?? $avaliacao->respostas->first()?->inscricao;
          $eventoNome = $inscricaoExibida?->evento?->nome ?? $atividade?->evento?->nome;
          $respostas = $respostasExistentes ?? collect();
          $formBloqueado = $jaRespondeu ?? false;
          $formularioFechado = $formularioFechado ?? false;
          $somenteVisualizacao = $somenteVisualizacao ?? false;
          $exigePresenca = ! $isUniversal && ! $isTranscricao;
        @endphp

        <div style="margin-bottom: 20px; font-size: 14px; line-height: 1.6;">
          @if($isUniversal)
            <p style="margin: 0;"><strong>Avaliação universal:</strong> {{ $avaliacao->descricao_universal ?: ($avaliacao->templateAvaliacao->nome ?? '-') }}</p>
          @elseif($isTranscricao)
            <p style="margin: 0;"><strong>Transcrição:</strong> {{ $avaliacao->descricao_universal ?: ($avaliacao->templateAvaliacao->nome ?? '-') }}</p>
            <p style="margin: 0;"><strong>Momento:</strong> {{ $atividade?->descricao ?? '-' }}</p>
          @else
            <p style="margin: 0;"><strong>Ação pedagógica:</strong> {{ $eventoNome ?? '-' }}</p>
            @if($avaliacao->descricao_universal)
              <p style="margin: 0;"><strong>Avaliação:</strong> {{ $avaliacao->descricao_universal }}</p>
            @endif
          @endif
        </div>

        @if($exigePresenca && empty($token))
          <div class="cpe-alert" style="background: #fff7e0; border-color: #e5c76d; color: #715200;">
            Confirme sua presença no momento para gerar o link pessoal desta avaliação.
          </div>
        @endif

        @if($formBloqueado)
          <div class="cpe-alert" style="background: rgba(0,139,188,.08); border-color: rgba(0,139,188,.25); color: #006d93;">
            {{ $formularioFechado ? 'Este formulário não está recebendo respostas no momento.' : 'Você já respondeu este formulário. Obrigado pelo retorno!' }}
          </div>
        @endif

        @if($somenteVisualizacao)
          <div class="cpe-alert" style="background: rgba(0,139,188,.08); border-color: rgba(0,139,188,.25); color: #006d93;">
            Pré-visualização do formulário. As respostas não podem ser enviadas nesta tela.
          </div>
        @endif

        <form method="POST" action="{{ $formAction }}">
          @csrf
          <input type="hidden" name="token" value="{{ old('token', $token) }}">
          @if(request('transcricao') || ($isTranscricao ?? false))
            <input type="hidden" name="transcricao" value="1">
          @endif

          <fieldset @disabled($formBloqueado || $somenteVisualizacao) style="border: 0; padding: 0; margin: 0;">
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

            @forelse($questoesAgrupadas as $dimensao => $indicadores)
              <div style="border-bottom: 1px solid var(--cpe-line); padding-bottom: 18px; margin-bottom: 18px;">
                <h2 style="font-size: 17px; font-weight: 800; color: var(--cpe-purple); margin: 0 0 10px;">
                  Dimensão — {{ $dimensao }}
                </h2>

                @foreach($indicadores as $indicador => $questoes)
                  <div style="margin-bottom: 12px;">
                    <p style="font-weight: 700; color: var(--cpe-blue); margin: 0 0 10px; font-size: 14px;">
                      Indicador — {{ $indicador }}
                    </p>

                    @foreach($questoes as $questao)
                      @php
                        $contador++;
                        $valorAtual = old("respostas.{$questao->id}", $respostas[$questao->id] ?? null);
                      @endphp
                      <div style="margin-bottom: 18px;">
                        <p style="font-weight: 700; margin: 0 0 6px; font-size: 14px;">
                          <span style="color: var(--cpe-muted); margin-right: 6px;">{{ $contador }}.</span>
                          {{ $questao->texto }}
                        </p>

                        <div style="margin-top: 8px;">
                          @switch($questao->tipo)
                            @case('numero')
                              <input type="number" step="any" name="respostas[{{ $questao->id }}]"
                                class="cpe-field" value="{{ $valorAtual }}" placeholder="Digite um número"
                                style="max-width: 280px;">
                              @break

                            @case('escala')
                              @php $opcoesEscala = collect($questao->escala?->valores ?? []); @endphp
                              @if($opcoesEscala->isNotEmpty())
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                  @foreach($opcoesEscala as $indice => $opcao)
                                    @php $inputId = 'q'.$questao->id.'_'.($indice + 1); @endphp
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                                      <input type="radio"
                                        name="respostas[{{ $questao->id }}]"
                                        id="{{ $inputId }}"
                                        value="{{ $opcao }}"
                                        {{ (string) $valorAtual === (string) $opcao ? 'checked' : '' }}
                                        style="accent-color: var(--cpe-purple); width: 16px; height: 16px;">
                                      {{ strip_tags($opcao) }}
                                    </label>
                                  @endforeach
                                </div>
                              @else
                                <p style="color: var(--cpe-muted); font-size: 13px;">Escala não configurada.</p>
                              @endif
                              @break

                            @case('boolean')
                              <div style="display: flex; flex-direction: column; gap: 8px;">
                                @php $inputSim = 'q'.$questao->id.'_sim'; $inputNao = 'q'.$questao->id.'_nao'; @endphp
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                                  <input type="radio" name="respostas[{{ $questao->id }}]" value="1" id="{{ $inputSim }}"
                                    {{ (string) $valorAtual === '1' ? 'checked' : '' }}
                                    style="accent-color: var(--cpe-purple); width: 16px; height: 16px;">
                                  Sim
                                </label>
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                                  <input type="radio" name="respostas[{{ $questao->id }}]" value="0" id="{{ $inputNao }}"
                                    {{ (string) $valorAtual === '0' ? 'checked' : '' }}
                                    style="accent-color: var(--cpe-purple); width: 16px; height: 16px;">
                                  Não
                                </label>
                              </div>
                              @break

                            @case('unica')
                              @php $opcoesResposta = collect($questao->opcoes_resposta ?? []); @endphp
                              @if($opcoesResposta->isNotEmpty())
                                <select name="respostas[{{ $questao->id }}]" class="cpe-select" style="max-width: 380px;">
                                  <option value="">Selecione...</option>
                                  @foreach($opcoesResposta as $opcao)
                                    <option value="{{ $opcao }}" @selected((string) $valorAtual === (string) $opcao)>{{ $opcao }}</option>
                                  @endforeach
                                </select>
                              @else
                                <p style="color: var(--cpe-muted); font-size: 13px;">Opções não configuradas.</p>
                              @endif
                              @break

                            @case('multipla')
                              @php
                                $respostasSelecionadas = is_array($valorAtual)
                                    ? $valorAtual
                                    : (json_decode($valorAtual, true) ?? []);
                              @endphp
                              <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 6px;">
                                @foreach($questao->opcoes_resposta as $index => $opcao)
                                  <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                                    <input type="checkbox"
                                           name="respostas[{{ $questao->id }}][]"
                                           value="{{ $opcao }}"
                                           id="q_{{ $questao->id }}_op_{{ $index }}"
                                           {{ in_array($opcao, $respostasSelecionadas) ? 'checked' : '' }}
                                           style="accent-color: var(--cpe-purple); width: 16px; height: 16px;">
                                    {{ $opcao }}
                                  </label>
                                @endforeach
                              </div>
                              @break

                            @default
                              <textarea name="respostas[{{ $questao->id }}]" class="cpe-textarea"
                                rows="3" placeholder="Compartilhe sua percepção"
                                style="min-height: 100px;">{{ $valorAtual }}</textarea>
                          @endswitch
                        </div>

                        @error("respostas.{$questao->id}")
                          <div style="color: #d04b4b; font-size: 12px; margin-top: 4px; font-weight: 600;">{{ $message }}</div>
                        @enderror
                      </div>
                    @endforeach
                  </div>
                @endforeach
              </div>
            @empty
              <p style="color: var(--cpe-muted); font-size: 14px; text-align: center; padding: 24px 0;">
                Nenhuma questão cadastrada para esta avaliação.
              </p>
            @endforelse
          </fieldset>

          @unless($formBloqueado || $somenteVisualizacao)
            <div style="text-align: right; margin-top: 24px;">
              <button type="submit" class="cpe-button" style="width: auto; padding: 0 32px;">
                Enviar avaliação
              </button>
            </div>
          @endunless
        </form>

      </div>
    </section>
  </main>
@endsection
