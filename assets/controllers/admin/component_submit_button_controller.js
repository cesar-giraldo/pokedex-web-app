import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'eager' */
export default class extends Controller {
    static values = {
        label: String,
        processingLabel: String,
        disabled: Boolean,
    };

    connect() {
        this.form = this.element.closest('form');
        this.isSubmitting = false;
        this.submitterField = null;
        this.onSubmit = this.handleSubmit.bind(this);
        this.onTurboSubmitEnd = this.handleTurboSubmitEnd.bind(this);

        if (this.form) {
            this.form.addEventListener('submit', this.onSubmit);
            this.form.addEventListener('turbo:submit-end', this.onTurboSubmitEnd);
        }
    }

    disconnect() {
        if (this.form) {
            this.form.removeEventListener('submit', this.onSubmit);
            this.form.removeEventListener('turbo:submit-end', this.onTurboSubmitEnd);
        }

        this.removeSubmitterField();
    }

    handleSubmit(event) {
        if (this.isSubmitting) {
            return;
        }

        if (this.disabledValue || this.element.disabled) {
            return;
        }

        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();
        this.lock();
        this.ensureSubmitterField();

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                this.isSubmitting = true;
                this.form.requestSubmit();
            });
        });
    }

    handleTurboSubmitEnd(event) {
        if (event.detail?.success) {
            return;
        }

        this.unlock();
    }

    lock() {
        this.element.disabled = true;
        this.element.setAttribute('aria-busy', 'true');
        this.element.textContent = this.processingLabelValue;
    }

    unlock() {
        this.isSubmitting = false;
        this.removeSubmitterField();
        this.element.disabled = this.disabledValue;
        this.element.removeAttribute('aria-busy');
        this.element.textContent = this.labelValue;
    }

    ensureSubmitterField() {
        if ('' === this.element.name || this.submitterField) {
            return;
        }

        const field = document.createElement('input');
        field.type = 'hidden';
        field.name = this.element.name;
        field.value = this.element.value;

        this.form.appendChild(field);
        this.submitterField = field;
    }

    removeSubmitterField() {
        if (!this.submitterField) {
            return;
        }

        this.submitterField.remove();
        this.submitterField = null;
    }
}
