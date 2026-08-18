import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['input', 'clientError'];

    static values = {
        pattern: String,
        patternFlags: String,
        patternErrorMessage: String,
        disabled: Boolean,
        required: Boolean,
        hasServerError: Boolean,
        uppercase: Boolean,
        lowercase: Boolean,
        onlyNumbers: Boolean,
        minLength: Number,
        maxLength: Number,
    };

    static onlyNumbersPattern = /^-?\d+(\.\d{1,2})?$/;

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

        this.applyCaseTransform();
        this.applyOnlyNumbersFilter();
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

        this.applyCaseTransform();
        this.applyOnlyNumbersFilter();
        this.clearClientError();
    }

    onBlur() {
        if (this.disabledValue) {
            return;
        }

        this.applyOnlyNumbersFilter(true);
        this.validate();
    }

    onInvalid(event) {
        event.preventDefault();
        this.validate();

        if (this.inputTarget.validity.valid) {
            this.clearClientError();
        } else {
            this.showClientError(this.inputTarget.validationMessage);
        }
    }

    validate() {
        const value = this.inputTarget.value;
        const trimmed = value.trim();

        if ('' === trimmed) {
            if (this.requiredValue) {
                this.inputTarget.setCustomValidity('Completa este campo.');
            } else {
                this.inputTarget.setCustomValidity('');
                this.clearClientError();
            }

            return this.inputTarget.checkValidity();
        }

        if (this.minLengthValue > 0 && trimmed.length < this.minLengthValue) {
            const message = `Introduce al menos ${this.minLengthValue} caracteres.`;
            this.showClientError(message);
            this.inputTarget.setCustomValidity(message);

            return false;
        }

        if (this.maxLengthValue > 0 && trimmed.length > this.maxLengthValue) {
            const message = `Introduce como máximo ${this.maxLengthValue} caracteres.`;
            this.showClientError(message);
            this.inputTarget.setCustomValidity(message);

            return false;
        }

        if (this.onlyNumbersValue) {
            const normalized = this.normalizeNumericValue(trimmed);

            if (normalized !== trimmed) {
                this.inputTarget.value = normalized;
            }

            if (!this.isValidOnlyNumbers(normalized)) {
                this.showClientError(this.patternErrorMessageValue);
                this.inputTarget.setCustomValidity(this.patternErrorMessageValue);

                return false;
            }
        } else if ('' !== this.patternValue && !this.matchesPattern(trimmed)) {
            this.showClientError(this.patternErrorMessageValue);
            this.inputTarget.setCustomValidity(this.patternErrorMessageValue);

            return false;
        }

        this.inputTarget.setCustomValidity('');
        this.clearClientError();

        return true;
    }

    matchesPattern(value) {
        try {
            const regex = new RegExp(this.patternValue, this.patternFlagsValue);

            return regex.test(value);
        } catch {
            return false;
        }
    }

    applyCaseTransform() {
        const { value } = this.inputTarget;

        if (this.uppercaseValue) {
            const transformed = value.toUpperCase();

            if (transformed !== value) {
                this.inputTarget.value = transformed;
            }

            return;
        }

        if (this.lowercaseValue) {
            const transformed = value.toLowerCase();

            if (transformed !== value) {
                this.inputTarget.value = transformed;
            }
        }
    }

    applyOnlyNumbersFilter(trimTrailingDot = false) {
        if (!this.onlyNumbersValue) {
            return;
        }

        let { value } = this.inputTarget;
        const sanitized = this.sanitizeNumericValue(value);

        if (trimTrailingDot) {
            value = this.normalizeNumericValue(sanitized);
        } else {
            value = sanitized;
        }

        if (value !== this.inputTarget.value) {
            this.inputTarget.value = value;
        }
    }

    sanitizeNumericValue(value) {
        let result = '';
        let hasDot = false;
        let decimalCount = 0;
        let hasMinus = false;

        for (const char of value) {
            if ('-' === char) {
                if (!hasMinus && '' === result) {
                    hasMinus = true;
                    result += char;
                }

                continue;
            }

            if (char >= '0' && char <= '9') {
                if (hasDot) {
                    if (decimalCount >= 2) {
                        continue;
                    }

                    decimalCount += 1;
                }

                result += char;

                continue;
            }

            if ('.' === char && !hasDot) {
                const integerPart = hasMinus ? result.slice(1) : result;

                if ('' === integerPart) {
                    continue;
                }

                hasDot = true;
                result += char;
            }
        }

        return result;
    }

    normalizeNumericValue(value) {
        return value.replace(/\.$/, '');
    }

    isValidOnlyNumbers(value) {
        return this.constructor.onlyNumbersPattern.test(value);
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
