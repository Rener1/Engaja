{{-- Formulário de resposta. O botão de envio fica no rodapé do modal (atributo form="$formId"). --}}
<form action="{{ $url }}" method="POST" id="{{ $formId }}">
  @csrf
  <input type="hidden" name="contexto" value="{{ $contexto['token'] }}">

  <h6 class="eg-autorizacao-secao">Termo de autorização · {{ $contexto['acao_nome'] }}</h6>
  <div class="eg-autorizacao-termo mb-3" tabindex="0">{{ $contexto['termo_texto'] }}</div>

  <fieldset>
    <legend class="eg-autorizacao-secao">Uso de imagem, voz e nome nesta ação</legend>
    <div class="d-flex flex-wrap gap-2">
      <input class="btn-check" type="radio" name="autorizado" value="1" id="sim-{{ $chave }}" required>
      <label class="btn btn-outline-success" for="sim-{{ $chave }}"><i class="bi bi-check-circle me-1"></i> Sim, autorizo</label>
      <input class="btn-check" type="radio" name="autorizado" value="0" id="nao-{{ $chave }}" required>
      <label class="btn btn-outline-danger" for="nao-{{ $chave }}"><i class="bi bi-x-circle me-1"></i> Não autorizo</label>
    </div>
  </fieldset>
  <p class="small text-muted mt-3 mb-0">A decisão vale somente para esta ação e pode ser alterada depois. Não altera as presenças.</p>
</form>
