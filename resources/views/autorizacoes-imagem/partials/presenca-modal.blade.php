@if($contextoImagem = session(\App\Services\AutorizacaoImagemContexto::FLASH_KEY))
  <div class="modal fade" id="autorizacaoImagemPresencaModal" tabindex="-1" aria-labelledby="autorizacaoImagemPresencaTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <x-ui.modal-header icon="camera" title="Uso de imagem, voz e nome" title-id="autorizacaoImagemPresencaTitulo" dismiss-label="Responder depois">
          <x-slot:subtitle>{{ $contextoImagem['acao_nome'] }}</x-slot:subtitle>
        </x-ui.modal-header>
        <div class="modal-body">
          <p>Sua presença está confirmada, <strong>{{ $contextoImagem['participante_nome'] }}</strong>.</p>
          <p>Esta decisão vale para a ação pedagógica <strong>{{ $contextoImagem['acao_nome'] }}</strong> e seus momentos.</p>
          <h6 class="eg-autorizacao-secao">Termo de autorização</h6>
          <div class="eg-autorizacao-termo mb-2" tabindex="0">{{ $contextoImagem['termo_texto'] }}</div>
          <p class="small text-muted mb-3">Recusar ou responder depois não altera sua presença.</p>
          <form method="POST" action="{{ route('presenca.autorizacao-imagem', $contextoImagem['atividade_id']) }}" id="autorizacaoImagemPresencaForm">
            @csrf
            <input type="hidden" name="contexto" value="{{ $contextoImagem['token'] }}">
            <fieldset>
              <legend class="eg-autorizacao-secao">Você autoriza o uso de imagem, voz e nome nesta ação?</legend>
              <div class="d-flex flex-wrap gap-2">
                <input class="btn-check" type="radio" name="autorizado" value="1" id="imagemSim" required>
                <label class="btn btn-outline-success" for="imagemSim"><i class="bi bi-check-circle me-1"></i> Sim, autorizo</label>
                <input class="btn-check" type="radio" name="autorizado" value="0" id="imagemNao" required>
                <label class="btn btn-outline-danger" for="imagemNao"><i class="bi bi-x-circle me-1"></i> Não autorizo</label>
              </div>
            </fieldset>
            <p class="alert alert-danger d-none mt-3 mb-0" id="autorizacaoImagemErro" role="alert"></p>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Responder depois</button>
          <button type="submit" class="btn btn-engaja" id="autorizacaoImagemConfirmar" form="autorizacaoImagemPresencaForm">Confirmar resposta</button>
        </div>
      </div>
    </div>
  </div>
  <noscript>
    <section class="card m-3"><div class="card-body">
      <h2 class="h5">Uso de imagem — {{ $contextoImagem['acao_nome'] }}</h2>
      <p style="white-space: pre-line">{{ $contextoImagem['termo_texto'] }}</p>
      <p>Sua presença continua confirmada mesmo sem responder.</p>
      <form method="POST" action="{{ route('presenca.autorizacao-imagem', $contextoImagem['atividade_id']) }}">
        @csrf
        <input type="hidden" name="contexto" value="{{ $contextoImagem['token'] }}">
        <label><input type="radio" name="autorizado" value="1" required> Sim, autorizo</label>
        <label><input type="radio" name="autorizado" value="0" required> Não autorizo</label>
        <button type="submit" class="btn btn-engaja">Confirmar resposta</button>
      </form>
    </div></section>
  </noscript>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const el = document.getElementById('autorizacaoImagemPresencaModal');
      const form = document.getElementById('autorizacaoImagemPresencaForm');
      const button = document.getElementById('autorizacaoImagemConfirmar');
      const error = document.getElementById('autorizacaoImagemErro');
      const modal = bootstrap.Modal.getOrCreateInstance(el);
      let enviando = false;
      button.disabled = true;
      form.addEventListener('change', () => { button.disabled = enviando || !form.querySelector('input[name="autorizado"]:checked'); });
      const abrir = () => {
        const outra = document.querySelector('.modal.show');
        if (outra && outra !== el) {
          outra.addEventListener('hidden.bs.modal', () => setTimeout(abrir, 0), { once: true });
        } else {
          modal.show();
        }
      };
      setTimeout(abrir, 0);
      el.addEventListener('hide.bs.modal', event => { if (enviando) event.preventDefault(); });
      el.addEventListener('hidden.bs.modal', () => document.dispatchEvent(new CustomEvent('autorizacao-imagem:encerrada')), { once: true });
      form.addEventListener('submit', async event => {
        event.preventDefault();
        if (enviando || !form.reportValidity()) return;
        enviando = true;
        button.disabled = true;
        error.classList.add('d-none');
        try {
          const response = await fetch(form.action, {
            method: 'POST', body: new FormData(form), credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          });
          const data = await response.json().catch(() => ({}));
          if (!response.ok) {
            throw new Error(data.message || 'Não foi possível salvar. Tente novamente. Sua presença continua confirmada.');
          }
          enviando = false;
          modal.hide();
        } catch (failure) {
          error.textContent = failure.message || 'Falha na conexão. Sua presença continua confirmada.';
          error.classList.remove('d-none');
        } finally {
          enviando = false;
          button.disabled = !form.querySelector('input[name="autorizado"]:checked');
        }
      });
    });
  </script>
@endif
