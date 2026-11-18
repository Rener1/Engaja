@extends('layouts.app')

@section('content')
<x-ui.page-hero contained eyebrow="Operações" eyebrow-icon="calendar3" :title="'Novo momento — '.$evento->nome">
    <x-slot:actions>
        <a href="{{ route('eventos.show', $evento) }}" class="eg-hero-btn">Voltar à ação pedagógica</a>
    </x-slot:actions>
</x-ui.page-hero>

<div class="container">
  @if ($errors->any())
    <div class="alert alert-danger"><strong>Ops!</strong> Verifique os campos abaixo.</div>
  @endif

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST" action="{{ route('eventos.atividades.store', $evento) }}" id="form-novo-momento">
        {{-- campos hidden para enviar checklist junto com o form --}}
        <div id="hidden-checklist-planejamento">
          @foreach(collect(old('checklist_planejamento', $marcadosPlanejamento ?? []))->map(fn($v) => (int) $v)->unique()->values() as $indice)
            <input type="hidden" name="checklist_planejamento[]" value="{{ $indice }}">
          @endforeach
        </div>
        <div id="hidden-checklist-encerramento"></div>
        @include('atividades._form', [
          'evento' => $evento,
          'atividadesCopiaveis' => $atividadesCopiaveis,
          'submitLabel' => 'Salvar momento'
        ])
      </form>
    </div>
  </div>
</div>

{{-- Modal Checklist de Encerramento --}}
<x-checklist-modal
    id="modalChecklistPosAcao"
    title="Checklist de Encerramento"
    btn-label="Confirmar e Salvar"
    tipo="encerramento"
    :marcados="[]"
    :items="[
        'Verifiquei se os municípios estão corretos?',
        'Confirmei a carga horária e os horários de início e término?',
        'O público esperado e os dados do momento estão preenchidos corretamente?'
    ]"
/>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-novo-momento');

    if (form) {
        form.addEventListener('submit', function (e) {
            // O checklist só deve aparecer quando os obrigatórios do momento já estiverem preenchidos.
            if (!this.checkValidity()) {
                this.reportValidity();
                return;
            }

            if (!this.dataset.checklistConfirmed) {
                e.preventDefault();
                const modal = new bootstrap.Modal(document.getElementById('modalChecklistPosAcao'));
                modal.show();
            }
        });
    }

    const btnConfirmarPos = document.querySelector('.js-checklist-confirm[data-modal="modalChecklistPosAcao"]');
    if (btnConfirmarPos) {
        btnConfirmarPos.addEventListener('click', function () {
            const container = document.getElementById('hidden-checklist-encerramento');
            container.innerHTML = '';

            // Espelha as marcações do modal em inputs hidden para o backend receber no submit.
            document.querySelectorAll('#modalChecklistPosAcao .js-checklist-item:checked').forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'checklist_encerramento[]';
                input.value = cb.dataset.index;
                container.appendChild(input);
            });

            const modalEl = document.getElementById('modalChecklistPosAcao');
            bootstrap.Modal.getInstance(modalEl)?.hide();
            form.dataset.checklistConfirmed = 'true';

            // Reenvia pelo mecanismo nativo para manter a validação HTML consistente.
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }
            form.submit();
        });
    }
});
</script>
@endpush
