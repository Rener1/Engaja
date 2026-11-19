@props([
    'id'          => 'checklistModal',
    'title'       => 'Checklist',
    'items'       => [],
    'btnLabel'    => 'Prosseguir',
    'tipo'        => 'planejamento',   {{-- 'planejamento' ou 'encerramento' --}}
    'marcados'    => [],               {{-- índices já salvos --}}
])

<style>
.checklist-card {
    cursor: pointer;
    border: 2px solid #dee2e6 !important;
    border-radius: 10px;
    padding: 14px 18px;
    transition: all 0.18s ease;
    user-select: none;
    position: relative;
}
.checklist-card:hover {
    border-color: #421944 !important;
    background-color: #f9f4fa !important;
}
.checklist-card.checked {
    border-color: #421944 !important;
    background-color: #421944 !important;
    color: #fff !important;
}
.checklist-card.checked .checklist-check-icon {
    opacity: 1;
    transform: scale(1);
}
.checklist-check-icon {
    opacity: 0;
    transform: scale(0.5);
    transition: all 0.15s ease;
    flex-shrink: 0;
    width: 22px;
    height: 22px;
    background: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #421944;
    font-size: 13px;
    font-weight: 900;
}
.checklist-card input[type="checkbox"] {
    display: none;
}
</style>

<div class="modal fade"
     id="{{ $id }}"
     tabindex="-1"
     aria-labelledby="{{ $id }}Label"
     aria-modal="true"
     role="dialog"
     data-bs-backdrop="static"
     data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">

            <x-ui.modal-header icon="list-check" :title="$title" :title-id="$id.'Label'" :dismissible="false" />

            <div class="modal-body pt-2">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <p class="text-muted small mb-0">
                        Marque os itens concluídos. Você pode salvar mesmo que não esteja 100%.
                    </p>
                    <span class="fw-semibold small js-counter text-engaja" data-modal="{{ $id }}">
                        {{ count($marcados) }} / {{ count($items) }}
                    </span>
                </div>

                <div class="progress mb-4" style="height: 5px; border-radius:99px;">
                    <div class="progress-bar js-progress"
                         data-modal="{{ $id }}"
                         role="progressbar"
                         style="width:{{ count($items) > 0 ? round(count($marcados)/count($items)*100) : 0 }}%; background-color:#421944;"
                         aria-valuenow="{{ count($items) > 0 ? round(count($marcados)/count($items)*100) : 0 }}"
                         aria-valuemin="0"
                         aria-valuemax="100">
                    </div>
                </div>

                <div class="vstack gap-2">
                    @foreach($items as $index => $item)
                    <label class="checklist-card d-flex align-items-center gap-3 {{ in_array($index, $marcados) ? 'checked' : '' }}"
                           data-modal="{{ $id }}">
                        <input class="js-checklist-item"
                               type="checkbox"
                               id="{{ $id }}_item_{{ $index }}"
                               data-modal="{{ $id }}"
                               data-total="{{ count($items) }}"
                               data-index="{{ $index }}"
                               {{ in_array($index, $marcados) ? 'checked' : '' }}>
                        <span class="checklist-check-icon">✓</span>
                        <span class="checklist-card-text">{{ $item }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="modal-footer gap-2 border-0 pt-0">
                <button type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">
                    Cancelar
                </button>
                {{-- Botão sempre habilitado --}}
                <button type="button"
                        class="btn btn-engaja js-checklist-confirm"
                        data-modal="{{ $id }}"
                        data-tipo="{{ $tipo }}">
                    {{ $btnLabel }}
                </button>
            </div>

        </div>
    </div>
</div>