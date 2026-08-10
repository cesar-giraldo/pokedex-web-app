import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['input', 'clientError', 'copyButton', 'copyLabel'];

    static values = {
        invalidEmailMessage: String,
        disabled: Boolean,
        required: Boolean,
        hasServerError: Boolean,
        copyLabel: String,
        copiedLabel: String,
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
        this.form = this.element.closest('form');
        this.copyResetTimeout = null;

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

        if (null !== this.copyResetTimeout) {
            window.clearTimeout(this.copyResetTimeout);
        }
    }

    onInput() {
        if (this.disabledValue) {
            return;
        }

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
        const email = this.inputTarget.value.trim();

        if ('' === email) {
            if (this.requiredValue) {
                this.inputTarget.setCustomValidity('Completa este campo.');
            } else {
                this.inputTarget.setCustomValidity('');
                this.clearClientError();
            }

            return this.inputTarget.checkValidity();
        }

        if (!this.isValidEmail(email)) {
            this.showClientError(this.invalidEmailMessageValue);
            this.inputTarget.setCustomValidity(this.invalidEmailMessageValue);

            return false;
        }

        this.inputTarget.setCustomValidity('');
        this.clearClientError();

        return true;
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    async copyToClipboard(event) {
        event.preventDefault();

        if (this.disabledValue || !this.hasCopyLabelTarget) {
            return;
        }

        const email = this.inputTarget.value.trim();

        if ('' === email) {
            this.inputTarget.focus();

            return;
        }

        try {
            await navigator.clipboard.writeText(email);
            this.showCopiedFeedback();
        } catch {
            this.fallbackCopy(email);
        }
    }

    fallbackCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'absolute';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            this.showCopiedFeedback();
        } catch {
            // Ignore clipboard failures silently.
        } finally {
            document.body.removeChild(textarea);
        }
    }

    showCopiedFeedback() {
        if (!this.hasCopyLabelTarget) {
            return;
        }

        this.copyLabelTarget.textContent = this.copiedLabelValue;

        if (null !== this.copyResetTimeout) {
            window.clearTimeout(this.copyResetTimeout);
        }

        this.copyResetTimeout = window.setTimeout(() => {
            this.copyLabelTarget.textContent = this.copyLabelValue;
            this.copyResetTimeout = null;
        }, 2000);
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
