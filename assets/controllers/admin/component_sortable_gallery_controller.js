/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

async function loadSortable() {
    const module = await import('sortablejs');
    const Sortable = module.default ?? module;

    if (typeof Sortable?.create === 'function') {
        return Sortable;
    }

    if (typeof module.create === 'function') {
        return module;
    }

    throw new Error('Sortable.create is not available');
}

export default class extends Controller {
    static targets = ['list', 'item', 'empty', 'feedback'];

    static values = {
        reorderUrl: String,
        csrfToken: String,
        deleteTitle: { type: String, default: 'Eliminar imagen' },
        deleteMessage: { type: String, default: '¿Seguro que deseas eliminar esta imagen?' },
        reorderSuccess: { type: String, default: 'El orden de las imágenes fue actualizado correctamente.' },
        deleteSuccess: { type: String, default: 'La imagen se eliminó correctamente.' },
    };

    connect() {
        this.pendingDeleteItem = null;
        this.bindConfirmDialog();
        void this.initializeSortable();
    }

    disconnect() {
        this.destroySortable();
        this.unbindConfirmDialog();
    }

    requestDelete(event) {
        event.preventDefault();
        event.stopPropagation();

        this.pendingDeleteItem = event.currentTarget.closest('[data-component-sortable-gallery-target="item"]');

        const dialogController = this.getConfirmDialogController();
        if (!dialogController) {
            return;
        }

        dialogController.open({
            title: this.deleteTitleValue,
            message: this.deleteMessageValue,
        });
    }

    getConfirmDialogController() {
        const dialog = this.element.querySelector('[data-controller~="component-confirm-dialog"]');
        if (!dialog) {
            return null;
        }

        return this.application.getControllerForElementAndIdentifier(dialog, 'component-confirm-dialog');
    }

    async onDeleteConfirmed() {
        const item = this.pendingDeleteItem;
        this.pendingDeleteItem = null;

        if (!item) {
            return;
        }

        const deleteUrl = item.dataset.deleteUrl;
        if (!deleteUrl) {
            return;
        }

        try {
            const response = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrfTokenValue,
                },
            });

            if (!response.ok) {
                this.showFeedback('No se pudo eliminar la imagen. Inténtalo de nuevo.', 'error');
                return;
            }

            const payload = await response.json();
            item.remove();
            this.syncEmptyState();
            this.showFeedback(payload.message || this.deleteSuccessValue, 'success');
        } catch {
            this.showFeedback('No se pudo eliminar la imagen. Inténtalo de nuevo.', 'error');
        }
    }

    onDeleteCancelled() {
        this.pendingDeleteItem = null;
    }

    async initializeSortable() {
        if (!this.hasListTarget) {
            return;
        }

        try {
            const Sortable = await loadSortable();
            this.sortable = Sortable.create(this.listTarget, {
                animation: 150,
                draggable: '[data-component-sortable-gallery-target="item"]',
                filter: '.js-image-delete',
                preventOnFilter: true,
                ghostClass: 'opacity-50',
                onEnd: (sortEvent) => {
                    if (sortEvent.oldIndex === sortEvent.newIndex) {
                        return;
                    }

                    void this.persistOrder();
                },
            });
        } catch (error) {
            console.error('[component-sortable-gallery] No se pudo inicializar SortableJS.', error);
        }
    }

    destroySortable() {
        if (this.sortable) {
            this.sortable.destroy();
            this.sortable = undefined;
        }
    }

    async persistOrder() {
        const ids = this.itemTargets
            .map((item) => Number.parseInt(item.dataset.imageId, 10))
            .filter((id) => Number.isInteger(id) && id > 0);

        try {
            const response = await fetch(this.reorderUrlValue, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfTokenValue,
                },
                body: JSON.stringify({ ids }),
            });

            if (!response.ok) {
                this.showFeedback('No se pudo actualizar el orden de las imágenes. Inténtalo de nuevo.', 'error');
                return;
            }

            const payload = await response.json();
            this.showFeedback(payload.message || this.reorderSuccessValue, 'success');
        } catch {
            this.showFeedback('No se pudo actualizar el orden de las imágenes. Inténtalo de nuevo.', 'error');
        }
    }

    syncEmptyState() {
        const isEmpty = this.itemTargets.length === 0;

        if (this.hasListTarget) {
            this.listTarget.classList.toggle('hidden', isEmpty);
        }

        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.toggle('hidden', !isEmpty);
        }
    }

    showFeedback(message, type) {
        if (!this.hasFeedbackTarget) {
            return;
        }

        const isSuccess = 'success' === type;
        const borderClass = isSuccess
            ? 'border-success-500 bg-success-50 dark:border-success-500/30 dark:bg-success-500/15'
            : 'border-error-500 bg-error-50 dark:border-error-500/30 dark:bg-error-500/15';
        const iconClass = isSuccess ? 'text-success-500' : 'text-error-500';
        const iconSvg = isSuccess
            ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" /></svg>'
            : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd" /></svg>';

        this.feedbackTarget.innerHTML = `
            <div
                data-controller="component-alert"
                data-component-alert-auto-hide-delay-value="5000"
                data-component-alert-dismissible-value="true"
                class="relative rounded-xl border ${borderClass} p-4 opacity-100"
                role="alert"
                aria-live="polite"
            >
                <button
                    type="button"
                    class="absolute top-3 right-3 inline-flex items-center justify-center rounded-md p-1 text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    data-action="component-alert#dismiss"
                    aria-label="Cerrar notificación"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <div class="flex items-start gap-3 pr-8">
                    <div class="-mt-0.5 shrink-0 ${iconClass}">
                        ${iconSvg}
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">${this.escapeHtml(message)}</p>
                </div>
            </div>
        `;

        this.feedbackTarget.classList.remove('hidden');
    }

    escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;

        return div.innerHTML;
    }

    bindConfirmDialog() {
        const dialog = this.element.querySelector('[data-controller~="component-confirm-dialog"]');
        if (!dialog) {
            return;
        }

        this.confirmDialogElement = dialog;
        this.boundConfirmHandler = this.onDeleteConfirmed.bind(this);
        this.boundCancelHandler = this.onDeleteCancelled.bind(this);
        this.confirmDialogElement.addEventListener('component-confirm-dialog:confirmed', this.boundConfirmHandler);
        this.confirmDialogElement.addEventListener('component-confirm-dialog:cancelled', this.boundCancelHandler);
    }

    unbindConfirmDialog() {
        if (this.confirmDialogElement && this.boundConfirmHandler) {
            this.confirmDialogElement.removeEventListener('component-confirm-dialog:confirmed', this.boundConfirmHandler);
        }

        if (this.confirmDialogElement && this.boundCancelHandler) {
            this.confirmDialogElement.removeEventListener('component-confirm-dialog:cancelled', this.boundCancelHandler);
        }

        this.confirmDialogElement = undefined;
        this.boundConfirmHandler = undefined;
        this.boundCancelHandler = undefined;
    }
}
