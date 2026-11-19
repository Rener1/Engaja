{{-- Modal global: foto de perfil (uma vez por sessão em telas iniciais) --}}
<div class="modal fade" id="modalFotoPerfilPrompt" tabindex="-1" aria-labelledby="modalFotoPerfilPromptLabel" aria-hidden="true"
  data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <x-ui.modal-header icon="camera" title="Foto de perfil" title-id="modalFotoPerfilPromptLabel" :dismissible="false" />
      <div class="modal-body">
        <p class="mb-4 text-muted">Adicione uma foto ao seu perfil para personalizar sua conta. Você pode enviar agora ou pular e fazer depois em &quot;Meu perfil&quot;.</p>

        <form method="POST" action="{{ route('profile.photo-prompt.store') }}" enctype="multipart/form-data" id="formFotoPerfilPrompt">
          @csrf
          <div class="mb-4">
            <label for="profile_photo_prompt_input" class="form-label">Imagem</label>
            <input id="profile_photo_prompt_input" type="file" name="profile_photo"
              class="form-control @error('profile_photo', 'photoPrompt') is-invalid @enderror"
              accept=".jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp">
            @error('profile_photo', 'photoPrompt')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">JPG, PNG, GIF ou WEBP — máx. 5 MB.</div>
          </div>
          <div class="d-flex justify-content-end gap-2 flex-wrap">
            <button type="submit" class="btn btn-outline-secondary"
              formaction="{{ route('profile.photo-prompt.skip') }}"
              formmethod="POST"
              formenctype="application/x-www-form-urlencoded"
              formnovalidate>Pular</button>
            <button type="submit" class="btn btn-engaja"><i class="bi bi-upload me-1"></i> Enviar foto</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('modalFotoPerfilPrompt');
    if (el && window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(el).show();
    }
  });
</script>
