/**
 * Campo "Página [n] de N" da paginação do servidor (resources/views/vendor/pagination/engaja).
 * Ao confirmar (Enter ou saída do campo), limita o valor a 1..N e navega para a página,
 * preservando os demais parâmetros da URL (filtros, ordenação, session_key etc.).
 */
const goToPage = (input) => {
    const max = Number(input.max) || 1;
    const current = Number(input.defaultValue) || 1;
    const page = Math.min(Math.max(Math.trunc(Number(input.value)) || current, 1), max);

    input.value = page;
    if (page === current) {
        return;
    }

    const url = new URL(input.dataset.baseUrl, window.location.origin);
    url.searchParams.set(input.dataset.pageName || "page", String(page));
    window.location.assign(url.toString());
};

document.addEventListener("keydown", (event) => {
    const input = event.target.closest?.("[data-eg-page-input]");
    if (input && event.key === "Enter") {
        event.preventDefault();
        goToPage(input);
    }
});

document.addEventListener(
    "blur",
    (event) => {
        const input = event.target.closest?.("[data-eg-page-input]");
        if (input) {
            goToPage(input);
        }
    },
    true,
);
