import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['view', 'editor', 'editButton', 'doneButton', 'modal', 'modalTitle', 'toggleError'];

    static values = {
        editMode: Boolean,
        modalOpen: Boolean,
        csrfToken: String,
        createUrl: String,
    };

    connect() {
        this.syncMode();

        if (this.modalOpenValue) {
            this.showModal();
        }
    }

    startEdit(event) {
        event.preventDefault();
        this.editModeValue = true;
        this.syncMode();
    }

    finishEdit(event) {
        event.preventDefault();
        this.editModeValue = false;
        this.syncMode();
    }

    openCreate(event) {
        event.preventDefault();
        const form = this.form();
        if (!form) {
            return;
        }

        form.action = this.createUrlValue;
        this.setField('module-name', '');
        this.setField('module-slug', '');
        this.setField('module-sort-order', '');
        this.setField('module-description', '');
        this.setParent('', []);
        this.clearErrors();
        this.modalTitleTarget.textContent = 'Agregar módulo';
        this.showModal();
    }

    openEdit(event) {
        event.preventDefault();
        const row = event.currentTarget.closest('[data-module-id]');
        const form = this.form();
        if (!row || !form) {
            return;
        }

        form.action = row.dataset.moduleUpdateUrl || form.action;
        this.setField('module-name', row.dataset.moduleName || '');
        this.setField('module-slug', row.dataset.moduleSlug || '');
        this.setField('module-sort-order', row.dataset.moduleSortOrder || '');
        this.setField('module-description', row.dataset.moduleDescription || '');
        this.setParent(row.dataset.moduleParentId || '', (row.dataset.moduleExcludedIds || '').split(','));
        this.clearErrors();
        this.modalTitleTarget.textContent = 'Editar módulo';
        this.showModal();
    }

    closeModal(event) {
        event.preventDefault();
        this.modalTarget.classList.add('hidden');
        this.modalTarget.classList.remove('flex');
    }

    async toggleEnabled(event) {
        const input = event.target;
        if (!(input instanceof HTMLInputElement) || input.type !== 'checkbox') {
            return;
        }

        const row = input.closest('[data-enabled-url]');
        if (!row || !row.dataset.enabledUrl) {
            return;
        }

        const previous = !input.checked;
        this.hideToggleError();

        try {
            const response = await fetch(row.dataset.enabledUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfTokenValue,
                },
                body: JSON.stringify({ enabled: input.checked }),
            });

            let payload = {};
            try {
                payload = await response.json();
            } catch {
                payload = {};
            }

            if (!response.ok) {
                input.checked = previous;
                this.syncToggleInput(input);
                this.showToggleError(payload.error || 'No se pudo actualizar el módulo.');

                return;
            }

            if (Array.isArray(payload.modules)) {
                payload.modules.forEach((item) => this.applyModuleState(item));
            }
        } catch {
            input.checked = previous;
            this.syncToggleInput(input);
            this.showToggleError('No se pudo actualizar el módulo.');
        }
    }

    syncMode() {
        this.viewTarget.classList.toggle('hidden', this.editModeValue);
        this.editorTarget.classList.toggle('hidden', !this.editModeValue);
        this.editButtonTarget.classList.toggle('hidden', this.editModeValue);
        this.doneButtonTarget.classList.toggle('hidden', !this.editModeValue);
    }

    showModal() {
        this.modalTarget.classList.remove('hidden');
        this.modalTarget.classList.add('flex');
    }

    form() {
        const form = this.element.querySelector('#module-form');

        return form instanceof HTMLFormElement ? form : null;
    }

    setField(id, value) {
        const field = this.element.querySelector(`#${id}`);
        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
            field.value = value;
        }
    }

    setParent(parentId, excludedIds) {
        const select = this.element.querySelector('#module-parent');
        if (!(select instanceof HTMLSelectElement)) {
            return;
        }

        const excluded = new Set(excludedIds.filter((id) => id !== ''));
        Array.from(select.options).forEach((option) => {
            option.disabled = excluded.has(option.value);
        });
        select.value = parentId;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    clearErrors() {
        const form = this.form();
        if (!form) {
            return;
        }

        form.querySelectorAll('[role="alert"]').forEach((alert) => {
            if (alert.hasAttribute('data-component-text-input-target')) {
                return;
            }

            alert.remove();
        });

        form.querySelectorAll('[data-controller~="component-text-input"]').forEach((element) => {
            const controller = this.application.getControllerForElementAndIdentifier(element, 'component-text-input');
            if (!controller) {
                return;
            }

            controller.hasServerErrorValue = false;
            controller.inputTarget.setCustomValidity('');
            controller.clearClientError();
        });
    }

    applyModuleState(item) {
        if (!item || typeof item.id === 'undefined') {
            return;
        }

        this.element.querySelectorAll(`[data-module-id="${item.id}"]`).forEach((row) => {
            const input = row.querySelector('input[type="checkbox"]');
            if (input instanceof HTMLInputElement) {
                input.checked = Boolean(item.isEnabled);
                input.disabled = Boolean(item.locked);
                this.syncToggleInput(input);
            }

            const viewName = row.querySelector('[data-module-view-name]');
            if (viewName) {
                viewName.classList.toggle('line-through', !item.isEnabled);
                viewName.classList.toggle('text-gray-400', !item.isEnabled);
                viewName.classList.toggle('text-gray-800', Boolean(item.isEnabled));
                viewName.classList.toggle('dark:text-white/90', Boolean(item.isEnabled));
            }
        });
    }

    syncToggleInput(input) {
        const toggleRoot = input.closest('[data-controller~="component-toggle-switch"]');
        if (!toggleRoot) {
            return;
        }

        const controller = this.application.getControllerForElementAndIdentifier(
            toggleRoot,
            'component-toggle-switch',
        );
        if (!controller) {
            return;
        }

        controller.disabledValue = input.disabled;
        controller.syncVisualState();
    }

    showToggleError(message) {
        this.toggleErrorTarget.textContent = message;
        this.toggleErrorTarget.classList.remove('hidden');
    }

    hideToggleError() {
        this.toggleErrorTarget.textContent = '';
        this.toggleErrorTarget.classList.add('hidden');
    }
}
