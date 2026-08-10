import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['input', 'box', 'icon'];

    static values = {
        disabled: Boolean,
    };

    static uncheckedClasses = ['border-gray-300', 'bg-transparent', 'dark:border-gray-700'];

    static checkedClasses = ['border-brand-500', 'bg-brand-500'];

    static disabledCheckedClasses = ['border-gray-200', 'bg-transparent', 'dark:border-gray-800'];

    static disabledUncheckedClasses = ['border-brand-500', 'bg-brand-500'];

    static errorClasses = ['border-error-300', 'dark:border-error-700'];

    static hoverClasses = ['hover:border-brand-500', 'dark:hover:border-brand-500'];

    connect() {
        this.syncVisualState();
    }

    syncVisualState() {
        const checked = this.inputTarget.checked;
        const disabled = this.disabledValue || this.inputTarget.disabled;
        const hasError = this.inputTarget.getAttribute('aria-invalid') === 'true';

        this.resetBoxClasses();

        if (disabled) {
            this.boxTarget.classList.add(
                ...(checked ? this.constructor.disabledCheckedClasses : this.constructor.disabledUncheckedClasses),
            );
        } else if (hasError) {
            this.boxTarget.classList.add(...this.constructor.errorClasses);

            if (checked) {
                this.boxTarget.classList.add(...this.constructor.checkedClasses);
            } else {
                this.boxTarget.classList.add(...this.constructor.uncheckedClasses, ...this.constructor.hoverClasses);
            }
        } else if (checked) {
            this.boxTarget.classList.add(...this.constructor.checkedClasses);
        } else {
            this.boxTarget.classList.add(...this.constructor.uncheckedClasses, ...this.constructor.hoverClasses);
        }

        this.iconTarget.classList.toggle('opacity-0', !checked);
    }

    resetBoxClasses() {
        const allClasses = [
            ...this.constructor.uncheckedClasses,
            ...this.constructor.checkedClasses,
            ...this.constructor.disabledCheckedClasses,
            ...this.constructor.disabledUncheckedClasses,
            ...this.constructor.errorClasses,
            ...this.constructor.hoverClasses,
        ];

        allClasses.forEach((className) => this.boxTarget.classList.remove(className));
    }
}
