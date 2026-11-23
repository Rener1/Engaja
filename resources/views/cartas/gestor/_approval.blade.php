<form id="approveBatchForm" method="POST" action="{{ route('cartas.mensagens.approve-batch') }}">
    @csrf
</form>

<div class="cpe-batch-approval">
    <div>
        <p id="batchApprovalHelp">Selecione as respostas que deseja aprovar.</p>
        <span id="selectedCountBadge" aria-live="polite">0 respostas selecionadas nesta página</span>
        <p id="batchApprovalFeedback" role="status" hidden></p>
    </div>
    <button type="button" id="btnApproveBatch" class="cpe-button" aria-describedby="batchApprovalHelp" disabled>
        Aprovar selecionadas (0)
    </button>
</div>

<div class="cpe-modal" id="approveBatchModal" role="dialog" aria-modal="true"
    aria-labelledby="approveBatchTitle" aria-describedby="approveBatchDescription">
    <div class="cpe-modal__backdrop"></div>
    <div class="cpe-modal__dialog">
        <h2 id="approveBatchTitle">Aprovar respostas selecionadas</h2>
        <p id="approveBatchDescription"></p>
        <div class="cpe-modal-actions">
            <button type="button" class="cpe-button cpe-button--ghost" data-modal-close>Cancelar</button>
            <button type="submit" id="confirmApproveBatch" form="approveBatchForm" class="cpe-button" disabled>
                Confirmar aprovação
            </button>
        </div>
    </div>
</div>
