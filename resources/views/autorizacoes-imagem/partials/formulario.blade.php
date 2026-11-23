<details>
  <summary>{{ $resposta ? 'Alterar resposta desta ação' : 'Registrar resposta desta ação' }}</summary>
  <div class="border rounded p-3 mt-2">
    <p><strong>{{ $contexto['acao_nome'] }}</strong></p>
    <details class="mb-3"><summary>Ler termo de autorização</summary><p style="white-space: pre-line">{{ $contexto['termo_texto'] }}</p></details>
    <form action="{{ $url }}" method="POST">
      @csrf
      <input type="hidden" name="contexto" value="{{ $contexto['token'] }}">
      <fieldset>
        <legend class="fs-6">Uso de imagem, voz e nome nesta ação</legend>
        <div class="form-check"><input class="form-check-input" type="radio" name="autorizado" value="1" id="sim-{{ $chave }}" required><label class="form-check-label" for="sim-{{ $chave }}">Sim, autorizo</label></div>
        <div class="form-check mb-3"><input class="form-check-input" type="radio" name="autorizado" value="0" id="nao-{{ $chave }}" required><label class="form-check-label" for="nao-{{ $chave }}">Não autorizo</label></div>
      </fieldset>
      <p class="small text-muted">A decisão vale somente para esta ação e pode ser alterada aqui. Não altera as presenças.</p>
      <button type="submit" class="btn btn-engaja btn-sm">Confirmar resposta</button>
    </form>
  </div>
</details>
