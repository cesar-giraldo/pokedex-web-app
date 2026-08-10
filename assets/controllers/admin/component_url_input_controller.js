import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['input', 'hiddenInput', 'clientError'];

    static values = {
        prefix: String,
        invalidUrlMessage: String,
        disabled: Boolean,
        required: Boolean,
        hasServerError: Boolean,
    };

    static inputErrorClasses = [
        'border-error-300',
        'focus:border-error-300',
        'focus:ring-error-500/10',
        'dark:border-error-700',
        'dark:focus:border-error-800',
    ];

    static inputDefaultClasses = [
        'border-gray-300',
        'focus:border-brand-300',
        'focus:ring-brand-500/10',
        'dark:border-gray-700',
        'dark:focus:border-brand-800',
    ];

    connect() {
        this.syncHiddenValue();
        this.form = this.element.closest('form');

        if (this.form) {
            this.onSubmit = (event) => {
                if (this.disabledValue) {
                    return;
                }

                if (!this.validate()) {
                    event.preventDefault();
                    this.inputTarget.focus();
                }
            };

            this.form.addEventListener('submit', this.onSubmit);
        }
    }

    disconnect() {
        if (this.form && this.onSubmit) {
            this.form.removeEventListener('submit', this.onSubmit);
        }
    }

    onInput() {
        if (this.disabledValue) {
            return;
        }

        this.syncHiddenValue();
        this.clearClientError();
    }

    onBlur() {
        if (this.disabledValue) {
            return;
        }

        this.validate();
    }

    onInvalid(event) {
        event.preventDefault();
        this.validate();
    }

    validate() {
        const path = this.inputTarget.value.trim();

        if ('' === path) {
            if (this.requiredValue) {
                this.hiddenInputTarget.setCustomValidity('Completa este campo.');
            } else {
                this.hiddenInputTarget.setCustomValidity('');
                this.clearClientError();
            }

            this.syncHiddenValue();

            return this.hiddenInputTarget.checkValidity();
        }

        const fullUrl = this.buildFullUrl(path);

        if (!this.isValidUrl(fullUrl)) {
            this.showClientError(this.invalidUrlMessageValue);
            this.hiddenInputTarget.setCustomValidity(this.invalidUrlMessageValue);

            return false;
        }

        this.hiddenInputTarget.setCustomValidity('');
        this.clearClientError();
        this.syncHiddenValue();

        return true;
    }

    buildFullUrl(path) {
        if (/^https?:\/\//i.test(path)) {
            return path;
        }

        return this.prefixValue + path;
    }

    isValidUrl(url) {
        try {
            const parsed = new URL(url);

            return 'http:' === parsed.protocol || 'https:' === parsed.protocol;
        } catch {
            return false;
        }
    }

    syncHiddenValue() {
        const path = this.inputTarget.value.trim();
        this.hiddenInputTarget.value = '' === path ? '' : this.buildFullUrl(path);
    }

    showClientError(message) {
        this.clientErrorTarget.textContent = message;
        this.clientErrorTarget.classList.remove('hidden');
        this.inputTarget.classList.remove(...this.constructor.inputDefaultClasses);
        this.inputTarget.classList.add(...this.constructor.inputErrorClasses);
        this.inputTarget.setAttribute('aria-invalid', 'true');
    }

    clearClientError() {
        this.clientErrorTarget.textContent = '';
        this.clientErrorTarget.classList.add('hidden');

        if (this.hasServerErrorValue) {
            return;
        }

        this.inputTarget.removeAttribute('aria-invalid');
        this.inputTarget.classList.remove(...this.constructor.inputErrorClasses);
        this.inputTarget.classList.add(...this.constructor.inputDefaultClasses);
    }
}
