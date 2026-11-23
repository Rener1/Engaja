<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
  @include('autorizacoes-imagem.partials.situacao-badge', ['resposta' => $resposta])
  @if($resposta)
    <span class="small text-muted">{{ $resposta->respondido_em->format('d/m/Y H:i') }} · {{ \App\Enums\OrigemAutorizacaoImagem::from($resposta->origem)->rotulo() }}</span>
  @endif
</div>
@if($resposta && $resposta->historico->isNotEmpty())
  <details class="eg-autorizacao-historico">
    <summary>Histórico das respostas</summary>
    <ul class="eg-autorizacao-historico__lista">
      @foreach($resposta->historico as $registro)
        <li>
          <span class="fw-semibold">{{ $registro->autorizado ? 'Autorizada' : 'Não autorizada' }}</span>
          <span class="text-muted">
            · {{ $registro->respondido_em->format('d/m/Y H:i:s') }}
            · {{ \App\Enums\OrigemAutorizacaoImagem::from($registro->origem)->rotulo() }}
            @if(($gestao ?? false) && $registro->registradoPor)
              · Registrado por {{ $registro->registradoPor->name }}
            @endif
          </span>
        </li>
      @endforeach
    </ul>
  </details>
@endif
