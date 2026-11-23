const initializedSelects = new WeakSet();

class MomentoSelect {
    constructor(root) {
        this.root = root;
        this.select = root.querySelector("[data-momento-native]");
        this.trigger = root.querySelector("[data-momento-trigger]");
        this.panel = root.querySelector("[data-momento-panel]");
        this.dateInput = root.querySelector("[data-momento-date]");
        this.clearDate = root.querySelector("[data-momento-clear-date]");
        this.list = root.querySelector("[data-momento-list]");
        this.status = root.querySelector("[data-momento-status]");
        this.error = root.querySelector("[data-momento-error]");
        this.serverError = this.error.textContent.trim();
        this.validationRequested = false;
        this.activeOption = null;

        const fragment = document.createDocumentFragment();
        this.options = Array.from(this.select.options)
            .filter((option) => option.value !== "")
            .map((option, index) => {
                const item = document.createElement("li");
                item.id = this.select.id + "-option-" + index;
                item.className = "momento-select__option";
                item.setAttribute("role", "option");
                item.dataset.momentoOption = "";
                item.dataset.value = option.value;
                item.dataset.date = option.dataset.date || "";
                item.textContent = option.textContent.trim();
                fragment.append(item);
                return item;
            });
        this.list.append(fragment);

        this.bindEvents();
        this.dateInput.value = this.select.selectedOptions[0]?.dataset.date || "";
        this.syncSelection();
        this.filterOptions();

        root.querySelector("[data-momento-label]").htmlFor = this.trigger.id;
        this.select.tabIndex = -1;
        this.select.setAttribute("aria-hidden", "true");
        root.classList.add("momento-select--enhanced");
        root.querySelector("[data-momento-ui]").hidden = false;
    }

    bindEvents() {
        this.trigger.addEventListener("click", () => {
            if (this.panel.hidden) this.open();
            else this.close();
        });
        this.trigger.addEventListener("keydown", (event) => {
            if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                event.preventDefault();
                this.open(true, event.key === "ArrowUp");
            }
        });
        this.dateInput.addEventListener("input", () => this.filterOptions());
        this.dateInput.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                this.list.focus();
            }
        });
        this.clearDate.addEventListener("click", () => {
            this.dateInput.value = "";
            this.filterOptions();
            this.dateInput.focus();
        });
        this.list.addEventListener("keydown", (event) => this.onListKeydown(event));
        this.list.addEventListener("click", (event) => {
            const option = event.target.closest("[data-momento-option]");
            if (option && this.list.contains(option)) this.choose(option);
        });
        this.root.addEventListener("keydown", (event) => {
            if (event.key === "Escape" && !this.panel.hidden) {
                event.preventDefault();
                event.stopPropagation();
                this.close(true);
            }
        });
        this.root.addEventListener("focusout", (event) => {
            if (!this.root.contains(event.relatedTarget)) this.close();
        });
        document.addEventListener("click", (event) => {
            if (!this.root.contains(event.target)) this.close();
        });
        this.select.addEventListener("change", () => {
            this.serverError = "";
            this.syncSelection();
            this.filterOptions();
        });
        this.select.addEventListener("invalid", (event) => {
            event.preventDefault();
            this.validationRequested = true;
            this.syncSelection();
            this.open();
        });
        this.select.form?.addEventListener("reset", (event) => {
            queueMicrotask(() => {
                if (event.defaultPrevented) return;
                this.validationRequested = false;
                this.serverError = "";
                this.dateInput.value = this.select.selectedOptions[0]?.dataset.date || "";
                this.syncSelection();
                this.filterOptions();
                this.close();
            });
        });
    }

    syncSelection() {
        this.root.querySelector("[data-momento-selected]").textContent =
            this.select.selectedOptions[0]?.textContent.trim() || this.select.options[0].textContent;
        this.trigger.disabled = this.select.matches(":disabled");

        const message = this.serverError ||
            (this.validationRequested && !this.select.validity.valid ? "Selecione um momento." : "");
        this.error.textContent = message;
        this.error.hidden = !message;
        this.trigger.classList.toggle("is-invalid", Boolean(message));
        this.select.classList.toggle("is-invalid", Boolean(message));
        this.trigger.setAttribute("aria-invalid", String(Boolean(message)));
        this.list.setAttribute("aria-required", String(this.select.required));
        if (message) this.trigger.setAttribute("aria-describedby", this.error.id);
        else this.trigger.removeAttribute("aria-describedby");
    }

    filterOptions() {
        const date = this.dateInput.value;
        const validDate = this.dateInput.validity.valid;
        this.visibleOptions = this.options.filter((option) => {
            const matches = validDate && (!date || option.dataset.date === date);
            option.hidden = !matches;
            return matches;
        });
        this.clearDate.disabled = !date && validDate;
        const count = this.visibleOptions.length;
        this.status.textContent = !validDate
            ? "Informe uma data válida."
            : count === 0
                ? (date ? "Nenhum momento encontrado para esta data." : "Nenhum momento disponível.")
                : count + (count === 1 ? " momento encontrado." : " momentos encontrados.");
        const selected = this.visibleOptions.find((option) => option.dataset.value === this.select.value);
        this.setActive(selected || this.visibleOptions[0] || null);
        this.options.forEach((option) => {
            option.setAttribute("aria-selected", String(option.dataset.value === this.select.value));
        });
    }

    setActive(option, scroll = false) {
        this.activeOption?.classList.remove("is-active");
        this.activeOption = option;
        if (option) {
            option.classList.add("is-active");
            this.list.setAttribute("aria-activedescendant", option.id);
            if (scroll) option.scrollIntoView({ block: "nearest" });
        } else {
            this.list.removeAttribute("aria-activedescendant");
        }
    }

    open(focusList = false, last = false) {
        if (this.select.matches(":disabled")) return;
        this.panel.hidden = false;
        this.trigger.setAttribute("aria-expanded", "true");
        this.filterOptions();
        if (last && !this.select.value) this.setActive(this.visibleOptions.at(-1) || null);
        if (focusList) this.list.focus();
        else this.dateInput.focus();
        this.activeOption?.scrollIntoView({ block: "nearest" });
    }

    close(restoreFocus = false) {
        this.panel.hidden = true;
        this.trigger.setAttribute("aria-expanded", "false");
        if (restoreFocus) this.trigger.focus();
    }

    choose(option) {
        if (option.hidden || this.select.matches(":disabled")) return;
        const changed = this.select.value !== option.dataset.value;
        this.select.value = option.dataset.value;
        if (changed) {
            this.select.dispatchEvent(new Event("input", { bubbles: true }));
            this.select.dispatchEvent(new Event("change", { bubbles: true }));
        }
        this.close(true);
    }

    onListKeydown(event) {
        const index = this.visibleOptions.indexOf(this.activeOption);
        let nextIndex;
        switch (event.key) {
            case "ArrowDown":
                nextIndex = Math.min(index + 1, this.visibleOptions.length - 1);
                break;
            case "ArrowUp":
                nextIndex = Math.max(index - 1, 0);
                break;
            case "Home":
                nextIndex = 0;
                break;
            case "End":
                nextIndex = this.visibleOptions.length - 1;
                break;
            case "Enter":
            case " ":
                event.preventDefault();
                if (this.activeOption) this.choose(this.activeOption);
                return;
            default:
                return;
        }
        event.preventDefault();
        this.setActive(this.visibleOptions[nextIndex] || null, true);
    }
}

export function initMomentoSelects(container = document) {
    container.querySelectorAll("[data-momento-select]").forEach((root) => {
        if (initializedSelects.has(root)) return;
        new MomentoSelect(root);
        initializedSelects.add(root);
    });
}
