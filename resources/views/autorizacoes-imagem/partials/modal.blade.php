{{--
    Modal de uma autorização: resposta atual, histórico e (se $podeResponder) o formulário.
    Parâmetros: chave, titulo, subtitulo, resposta, gestao, podeResponder, contexto, url.
--}}
@php
    $modalId = 'modalAutorizacaoImagem-'.$chave;
    $formId = 'formAutorizacaoImagem-'.$chave;
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-titulo" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <x-ui.modal-header icon="camera" :title="$titulo" :title-id="$modalId.'-titulo'">
        <x-slot:subtitle>{{ $subtitulo }}</x-slot:subtitle>
      </x-ui.modal-header>
      <div class="modal-body">
        <h6 class="eg-autorizacao-secao">Resposta atual</h6>
        @include('autorizacoes-imagem.partials.resposta', ['resposta' => $resposta, 'gestao' => $gestao])

        @if($podeResponder)
          <hr class="my-4">
          @include('autorizacoes-imagem.partials.formulario', ['contexto' => $contexto, 'url' => $url, 'chave' => $chave, 'formId' => $formId])
        @endif
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ $podeResponder ? 'Cancelar' : 'Fechar' }}</button>
        @if($podeResponder)
          <button type="submit" class="btn btn-engaja" form="{{ $formId }}">Confirmar resposta</button>
        @endif
      </div>
    </div>
  </div>
</div>
