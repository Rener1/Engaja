<p class="mb-1"><strong>{{ \App\Enums\SituacaoAutorizacaoImagem::daResposta($resposta)->rotulo() }}</strong></p>
@if($resposta)
  <p class="small text-muted mb-1">{{ $resposta->respondido_em->format('d/m/Y H:i') }} · {{ \App\Enums\OrigemAutorizacaoImagem::from($resposta->origem)->rotulo() }}</p>
  <details class="small mb-2"><summary>Histórico das respostas</summary>
    @foreach($resposta->historico as $registro)
      <div class="border-bottom py-2">
        {{ $registro->respondido_em->format('d/m/Y H:i:s') }} — {{ $registro->autorizado ? 'Autorizada' : 'Não autorizada' }}
        · {{ \App\Enums\OrigemAutorizacaoImagem::from($registro->origem)->rotulo() }}
        @if(($gestao ?? false) && $registro->registradoPor)
          · Registrado por {{ $registro->registradoPor->name }}
        @endif
      </div>
    @endforeach
  </details>
@endif
