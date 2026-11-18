import "./bootstrap";
import "../css/graficos.css";
import "./graficos-ranking-municipios";
import "./graficos-distribuicao-dimensoes";
import "./lib/ag-grid-table";
import "./lib/segmented-control";
import "./lib/pagination";
import "tom-select/dist/css/tom-select.bootstrap5.min.css";
import TomSelect from "tom-select";

// import bundle com Popper e exporta classes Bootstrap
import * as bootstrap from "bootstrap";
import Quill from "quill";
import "quill/dist/quill.snow.css";

window.bootstrap = bootstrap;
window.TomSelect = TomSelect;
window.Quill = Quill;

const loadMultiSelectsIfNeeded = () => {
    document.querySelectorAll("[data-multiselect]").forEach((el) => {
        if (el.tomselect) {
            return;
        }

        new TomSelect(el, {
            plugins: ["remove_button"],
            hideSelected: true,
            closeAfterSelect: false,
            maxOptions: null,
            create: false,
            placeholder: el.dataset.placeholder || "Selecione...",
        });
    });
};

const loadReportMomentSelectIfNeeded = () => {
    const actionEl = document.getElementById("filter-acao-relatorio");
    const momentEl = document.getElementById("filter-momento-relatorio");
    const optionsEl = document.getElementById("relatorio-momentos-options");

    if (!actionEl || !momentEl || !optionsEl || momentEl.tomselect) {
        return;
    }

    const allMoments = JSON.parse(optionsEl.textContent || "[]");
    const actions = actionEl.tomselect;
    const moments = new TomSelect(momentEl, {
        plugins: ["remove_button"],
        hideSelected: true,
        closeAfterSelect: false,
        maxOptions: null,
        create: false,
        placeholder: momentEl.dataset.placeholder,
    });

    const updateMoments = () => {
        const selectedActions = new Set(actions?.items.map(String) || []);
        const allowedMoments = allMoments.filter((moment) =>
            selectedActions.has(String(moment.acao_id)),
        );
        const allowedValues = new Set(allowedMoments.map((moment) => String(moment.value)));
        const selectedMoments = moments.items.filter((value) =>
            allowedValues.has(String(value)),
        );

        moments.clear(true);
        moments.clearOptions();

        if (selectedActions.size === 0) {
            moments.settings.placeholder = "Selecione primeiro uma ação";
            moments.disable();
            moments.inputState();
            return;
        }

        moments.settings.placeholder = "Todos os momentos";
        moments.enable();
        moments.addOptions(allowedMoments);
        moments.setValue(selectedMoments, true);
        moments.refreshOptions(false);
        moments.inputState();
    };

    actions?.on("change", updateMoments);
    updateMoments();
};

// Carrega Fabric.js apenas nas telas que têm o canvas de certificado
const loadFabricIfNeeded = () => {
    const hasCanvas =
        document.getElementById("canvas-frente") ||
        document.getElementById("canvas-verso");

    if (!hasCanvas || window.fabric) return;

    import("fabric").then((mod) => {
        const fabric = mod.fabric || mod.default || mod;
        window.fabric = fabric;
        document.dispatchEvent(new Event("fabric:ready"));
    });
};

// Carrega o pdf.js apenas nas telas de Cartas que exibem uma carta em PDF
const loadCartasPdfIfNeeded = () => {
    if (!document.querySelector(".cpe-letter-doc[data-pdf-src]")) {
        return;
    }

    import("./cartas-pdf-viewer");
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", loadMultiSelectsIfNeeded, {
        once: true,
    });
    document.addEventListener("DOMContentLoaded", loadReportMomentSelectIfNeeded, {
        once: true,
    });
    document.addEventListener("DOMContentLoaded", loadFabricIfNeeded, {
        once: true,
    });
    document.addEventListener("DOMContentLoaded", loadCartasPdfIfNeeded, {
        once: true,
    });
} else {
    loadMultiSelectsIfNeeded();
    loadReportMomentSelectIfNeeded();
    loadFabricIfNeeded();
    loadCartasPdfIfNeeded();
}

let confirmModalInstance;
let confirmMessageEl;
let confirmAcceptBtn;
let pendingForm = null;

const submitWithConfirmation = () => {
    if (!pendingForm) {
        return;
    }

    pendingForm.dataset.confirmed = "true";

    const formToSubmit = pendingForm;
    pendingForm = null;

    if (typeof formToSubmit.requestSubmit === "function") {
        formToSubmit.requestSubmit();
    } else {
        formToSubmit.submit();
    }
};

const ensureModalSetup = () => {
    if (confirmModalInstance || !bootstrap?.Modal) {
        return;
    }

    const modalEl = document.getElementById("confirmModal");
    if (!modalEl) {
        return;
    }

    confirmModalInstance = new bootstrap.Modal(modalEl);
    confirmMessageEl = modalEl.querySelector(".js-confirm-message");
    confirmAcceptBtn = modalEl.querySelector(".js-confirm-accept");

    confirmAcceptBtn?.addEventListener("click", () => {
        confirmModalInstance?.hide();
        submitWithConfirmation();
    });
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", ensureModalSetup, {
        once: true,
    });
} else {
    ensureModalSetup();
}

document.addEventListener("submit", (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const confirmMessage = form.dataset.confirm;

    if (!confirmMessage) {
        return;
    }

    if (form.dataset.confirmed === "true") {
        delete form.dataset.confirmed;
        return;
    }

    event.preventDefault();
    pendingForm = form;

    ensureModalSetup();

    if (confirmMessageEl) {
        confirmMessageEl.textContent = confirmMessage;
    }

    if (confirmModalInstance) {
        confirmModalInstance.show();
    } else if (window.confirm(confirmMessage)) {
        submitWithConfirmation();
    } else {
        pendingForm = null;
    }
});

let blockedDeleteModalInstance = null;
let blockedDeleteTitleEl = null;
let blockedDeleteMessageEl = null;

const ensureBlockedDeleteModalSetup = () => {
    if (blockedDeleteModalInstance || !bootstrap?.Modal) {
        return;
    }

    const modalEl = document.getElementById("blockedDeleteModal");
    if (!modalEl) {
        return;
    }

    blockedDeleteModalInstance = new bootstrap.Modal(modalEl);
    blockedDeleteTitleEl = modalEl.querySelector(".js-blocked-delete-title");
    blockedDeleteMessageEl = modalEl.querySelector(".js-blocked-delete-message");
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", ensureBlockedDeleteModalSetup, {
        once: true,
    });
} else {
    ensureBlockedDeleteModalSetup();
}

document.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-blocked-delete]");
    if (!trigger) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    ensureBlockedDeleteModalSetup();

    const blockedMessage = trigger.dataset.blockedDelete;
    const blockedTitle = trigger.dataset.blockedDeleteTitle;

    if (blockedDeleteMessageEl && blockedMessage) {
        blockedDeleteMessageEl.textContent = blockedMessage;
    }
    if (blockedDeleteTitleEl && blockedTitle) {
        blockedDeleteTitleEl.textContent = blockedTitle;
    }

    if (blockedDeleteModalInstance) {
        blockedDeleteModalInstance.show();
    } else {
        const modalEl = document.getElementById("blockedDeleteModal");
        if (modalEl && window.bootstrap?.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            window.alert(blockedMessage || "Este momento possui presenças associadas e não pode ser excluído.");
        }
    }
});

