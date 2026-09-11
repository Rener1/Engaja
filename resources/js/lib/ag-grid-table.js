import { AllCommunityModule, ModuleRegistry, createGrid } from "ag-grid-community";

ModuleRegistry.registerModules([AllCommunityModule]);

const htmlCellRenderer = (params) => params.value ?? "";

// Cabeçalho com HTML cru (ex.: o link de ordenação server-side já usado nos
// relatórios). Usado quando a coluna não deve ter sort nativo do grid — quem
// decide a ordenação é o link, via reload de página com querystring.
class HtmlHeaderComponent {
    init(params) {
        this.eGui = document.createElement("div");
        this.eGui.className = "dt-header-html";
        this.eGui.innerHTML = params.headerHtml ?? "";
    }

    getGui() {
        return this.eGui;
    }

    refresh() {
        return false;
    }
}

// Renderer para linhas "full-width" (uma única célula ocupando a largura
// inteira do grid) usado para simular master/detail sem AG Grid Enterprise:
// a página insere/remove essas linhas via applyTransaction quando o usuário
// expande/colapsa uma linha mestre. O conteúdo vem de `data.detailHtml`.
class FullWidthHtmlRenderer {
    init(params) {
        this.eGui = document.createElement("div");
        this.eGui.className = "dt-detail-row";
        this.eGui.innerHTML = params.data?.detailHtml ?? "";
    }

    getGui() {
        return this.eGui;
    }

    refresh(params) {
        this.eGui.innerHTML = params.data?.detailHtml ?? "";
        return true;
    }
}

const ALIGN_TO_JUSTIFY = { start: "flex-start", end: "flex-end", center: "center" };
const ALIGN_TO_TEXT_CLASS = { start: "text-start", end: "text-end", center: "text-center" };

const buildColumnDefs = (columns, rowClassField) =>
    columns.map((col) => {
        if (col.children) {
            return {
                headerName: col.headerName,
                groupId: col.groupId ?? col.headerName,
                marryChildren: true,
                children: buildColumnDefs(col.children, rowClassField),
            };
        }

        const def = {
            field: col.field,
            headerName: col.headerName,
            colId: col.field,
            sortable: col.html ? false : col.sortable ?? true,
            filter: false,
            resizable: col.resizable ?? true,
            hide: col.hide ?? false,
            flex: col.flex ?? 1,
            // Sem um piso, `sizeColumnsToFit()` espreme colunas até ficarem
            // ilegíveis em telas estreitas (ex.: cabeçalhos cortados para 2-3
            // letras no mobile) em vez de habilitar o scroll horizontal do
            // grid. Colunas que precisam de mais espaço já passam `minWidth`
            // explícito (ex.: nomes longos, botões de ação).
            minWidth: col.minWidth ?? 100,
            width: col.width,
            pinned: col.pinned,
            cellClass: col.cellClass,
            headerClass: col.headerClass,
            // Reordenar colunas via drag nunca foi um requisito (as tabelas
            // Blade originais tinham ordem fixa) e o comportamento nativo do
            // AG Grid fica inconsistente entre colunas com headerComponent
            // customizado (sort link server-side) e colunas normais —
            // desabilitar em todas evita essa inconsistência.
            suppressMovable: true,
        };

        if (col.headerHtml) {
            def.headerComponent = HtmlHeaderComponent;
            def.headerComponentParams = { headerHtml: col.headerHtml };
        }

        // Mescla esta célula com as N colunas seguintes quando a linha tem o
        // rowClass indicado (ex.: linhas de "Subtotal"/"TOTAL" que mostram um
        // rótulo único ocupando o espaço de várias colunas, como no colspan
        // das tabelas Blade originais).
        if (col.colSpanWhen && col.colSpanCount) {
            const matchValues = Array.isArray(col.colSpanWhen) ? col.colSpanWhen : [col.colSpanWhen];
            def.colSpan = (params) =>
                rowClassField && matchValues.includes(params.data?.[rowClassField]) ? col.colSpanCount : 1;
        }

        if (col.html) {
            def.cellRenderer = htmlCellRenderer;
            def.cellStyle = {
                display: "flex",
                alignItems: "center",
                justifyContent: ALIGN_TO_JUSTIFY[col.align] ?? "flex-start",
                height: "100%",
                minWidth: "0",
                width: "100%",
                overflow: col.overflow ?? "hidden",
                // O tema do AG Grid aplica line-height pensado pra texto de uma
                // linha só; quando o HTML da célula tem 2+ linhas (ex.: data e
                // "até X" embaixo), isso dobra a altura do conteúdo e o conteúdo
                // estoura a linha. "normal" deixa cada linha com sua altura real.
                lineHeight: "normal",
            };
        } else {
            // O AG Grid centraliza células de texto via line-height (display:
            // block), que não alinha verticalmente com colunas HTML (que usam
            // flex). Forçar o mesmo mecanismo flex aqui também, preservando a
            // truncagem com "...": min-width:0 deixa o item flex encolher
            // (senão o flex impede o overflow:hidden/text-overflow:ellipsis
            // já aplicado pela classe ag-cell-value de funcionar).
            def.cellStyle = {
                display: "flex",
                alignItems: "center",
                justifyContent: ALIGN_TO_JUSTIFY[col.align] ?? "flex-start",
                minWidth: "0",
            };

            // Trunca com "..." (comportamento padrão do AG Grid para células de
            // texto) e mostra o conteúdo completo em tooltip só quando truncado.
            def.tooltipValueGetter = (params) =>
                params.value !== undefined && params.value !== null && params.value !== ""
                    ? String(params.value)
                    : undefined;

            if (col.align) {
                def.cellClass = [col.cellClass, ALIGN_TO_TEXT_CLASS[col.align]].filter(Boolean).join(" ");
            }
        }

        return def;
    });

const initTable = (el) => {
    const columns = JSON.parse(el.dataset.columns || "[]");
    const rows = JSON.parse(el.dataset.rows || "[]");
    const pagination = el.dataset.pagination !== "false";
    const pageSize = Number(el.dataset.pageSize || 15);
    const rowSelectionMode = el.dataset.rowSelection || null;
    const domLayout = el.dataset.domLayout || "autoHeight";
    const rowClassField = el.dataset.rowClassField || null;
    const idField = el.dataset.idField || "id";
    const selectedIds = JSON.parse(el.dataset.selectedIds || "[]").map(String);
    const rowSelectableField = el.dataset.rowSelectableField || null;
    const detailRowField = el.dataset.detailRowField || null;
    const detailRowHeight = Number(el.dataset.detailRowHeight || 420);

    const hasHtmlColumn = columns.some((col) => col.html);

    const gridOptions = {
        columnDefs: buildColumnDefs(columns, rowClassField),
        rowData: rows,
        pagination,
        paginationPageSize: pageSize,
        paginationPageSizeSelector: false,
        domLayout,
        rowHeight: hasHtmlColumn ? 52 : undefined,
        suppressCellFocus: true,
        autoSizeStrategy: {
            type: "fitGridWidth",
        },
        tooltipShowMode: "whenTruncated",
        tooltipShowDelay: 300,
        localeText: {
            page: "Página",
            to: "até",
            of: "de",
            noRowsToShow: "Nenhum registro encontrado.",
            first: "Primeira",
            previous: "Anterior",
            next: "Próxima",
            last: "Última",
        },
        onGridReady: (event) => {
            event.api.sizeColumnsToFit();
            if (rowSelectionMode && selectedIds.length) {
                event.api.forEachNode((node) => {
                    if (selectedIds.includes(String(node.data?.[idField]))) {
                        node.setSelected(true);
                    }
                });
            }
            el.dispatchEvent(new CustomEvent("datatable:ready", { detail: { api: event.api } }));
        },
    };

    if (rowSelectionMode) {
        gridOptions.rowSelection = {
            mode: rowSelectionMode === "multiple" ? "multiRow" : "singleRow",
            checkboxes: true,
            headerCheckbox: rowSelectionMode === "multiple",
        };
        gridOptions.selectionColumnDef = {
            width: 38,
            minWidth: 38,
            maxWidth: 38,
            resizable: false,
        };
        gridOptions.getRowId = (params) => String(params.data?.[idField]);

        if (rowSelectableField) {
            gridOptions.rowSelection.isRowSelectable = (rowNode) => !!rowNode.data?.[rowSelectableField];
        }

        gridOptions.onSelectionChanged = (event) => {
            const selectedRows = event.api.getSelectedRows();
            el.dispatchEvent(
                new CustomEvent("datatable:selection-changed", { detail: { rows: selectedRows } }),
            );
        };
    }

    if (rowClassField) {
        gridOptions.getRowClass = (params) => params.data?.[rowClassField] || undefined;
    }

    if (detailRowField) {
        gridOptions.getRowId = gridOptions.getRowId ?? ((params) => String(params.data?.[idField]));
        // isFullWidthRow só recebe `rowNode` (não `data`) nos params.
        gridOptions.isFullWidthRow = (params) => !!params.rowNode?.data?.[detailRowField];
        gridOptions.fullWidthCellRenderer = FullWidthHtmlRenderer;
        gridOptions.embedFullWidthRows = true;
        // Altura inicial pequena (cabe o spinner de "carregando"); depois que o
        // conteúdo real chega, a página mede a altura natural e ajusta via
        // node.setRowHeight()/api.onRowHeightChanged(), respeitando o teto de
        // detailRowHeight (acima disso o .dt-detail-row rola internamente).
        gridOptions.getRowHeight = (params) =>
            params.data?.[detailRowField] ? 70 : hasHtmlColumn ? 52 : undefined;

        if (!gridOptions.onGridReady) {
            gridOptions.onGridReady = (event) => {
                el.dispatchEvent(new CustomEvent("datatable:ready", { detail: { api: event.api } }));
            };
        }
    }

    el.dataset.agGridInitialized = "true";
    el._agGridApi = createGrid(el, gridOptions);
};

const initTablesIfNeeded = () => {
    document.querySelectorAll("[data-ag-grid]").forEach((el) => {
        if (el.dataset.agGridInitialized) {
            return;
        }

        initTable(el);
    });
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initTablesIfNeeded, { once: true });
} else {
    initTablesIfNeeded();
}

// AG Grid posiciona linhas com `transform: translateY(...)` para virtualização,
// o que cria um containing block próprio e quebra `position: fixed` em dropdowns
// dentro de células — o menu fica preso ao viewport scrollável do grid em vez do
// viewport da página. Solução: "portar" o menu para o <body> enquanto estiver
// aberto, para que o Popper o posicione relativo à página, e devolvê-lo ao lugar
// original ao fechar.
const portaledMenus = new WeakMap();

document.addEventListener("show.bs.dropdown", (event) => {
    const toggle = event.target;

    if (!(toggle instanceof HTMLElement) || !toggle.closest(".ag-cell")) {
        return;
    }

    const wrapper = toggle.closest(".dropdown");
    const menu = wrapper?.querySelector(".dropdown-menu");

    if (!wrapper || !menu) {
        return;
    }

    portaledMenus.set(wrapper, menu);
    document.body.appendChild(menu);
});

document.addEventListener("hidden.bs.dropdown", (event) => {
    const toggle = event.target;

    if (!(toggle instanceof HTMLElement)) {
        return;
    }

    const wrapper = toggle.closest(".dropdown");
    const menu = wrapper && portaledMenus.get(wrapper);

    if (wrapper && menu) {
        wrapper.appendChild(menu);
        portaledMenus.delete(wrapper);
    }
});
