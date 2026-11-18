/**
 * Segmented control (<x-ui.segmented>): alternância em "pílula" ligada a um
 * input hidden. Ao clicar, marca o botão ativo, atualiza o hidden e dispara
 * "change" nele (para scripts da página reagirem). Com data-autosubmit,
 * submete o formulário com feedback de carregamento.
 */
const initSegmentedControls = (root = document) => {
    root.querySelectorAll("[data-eg-segmented]").forEach((group) => {
        if (group.dataset.egSegmentedReady) {
            return;
        }
        group.dataset.egSegmentedReady = "1";

        const input = group.querySelector('input[type="hidden"]');
        const buttons = group.querySelectorAll(".eg-seg-btn");

        buttons.forEach((btn) => {
            btn.addEventListener("click", () => {
                buttons.forEach((b) => {
                    b.classList.toggle("active", b === btn);
                    b.setAttribute("aria-pressed", b === btn ? "true" : "false");
                });

                if (!input) {
                    return;
                }

                input.value = btn.dataset.value ?? "";
                input.dispatchEvent(new Event("change", { bubbles: true }));

                if (group.hasAttribute("data-autosubmit") && input.form) {
                    document.body.classList.add("eg-loading");
                    input.form.requestSubmit();
                }
            });
        });
    });
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initSegmentedControls());
} else {
    initSegmentedControls();
}

window.initSegmentedControls = initSegmentedControls;

export default initSegmentedControls;
