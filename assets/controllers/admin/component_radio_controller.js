import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['input', 'box', 'dot'];

    static values = {
        disabled: Boolean,
    };

    static uncheckedClasses = ['border-gray-300', 'bg-transparent', 'dark:border-gray-700'];

    static checkedClasses = ['border-brand-500', 'bg-brand-500'];

    static disabledCheckedClasses = ['border-gray-300', 'bg-transparent', 'dark:border-gray-700'];

    static disabledUncheckedClasses = ['border-brand-500', 'bg-brand-500'];

    static errorClasses = ['border-error-300', 'dark:border-error-700'];

    static hoverClasses = ['hover:border-brand-500', 'dark:hover:border-brand-500'];

    static dotCheckedClasses = ['bg-white'];

    static dotUncheckedClasses = ['bg-white', 'dark:bg-[#171f2e]'];

    connect() {
        this.syncVisualState();
    }

    syncGroup() {
        const groupName = this.inputTarget.name;

        if ('' === groupName) {
            this.syncVisualState();

            return;
        }

        document
            .querySelectorAll(`input[type="radio"][name="${CSS.escape(groupName)}"]`)
            .forEach((input) => {
                const element = input.closest('[data-controller~="component-radio"]');

                if (!element) {
                    return;
                }

                const controller = this.application.getControllerForElementAndIdentifier(
                    element,
                    'component-radio',
                );

                controller?.syncVisualState();
            });
    }

    syncVisualState() {
        const checked = this.inputTarget.checked;
        const disabled = this.disabledValue || this.inputTarget.disabled;
        const hasError = this.inputTarget.getAttribute('aria-invalid') === 'true';

        this.resetBoxClasses();
        this.resetDotClasses();

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

        this.dotTarget.classList.add(
            ...(checked ? this.constructor.dotCheckedClasses : this.constructor.dotUncheckedClasses),
        );
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

    resetDotClasses() {
        [...this.constructor.dotCheckedClasses, ...this.constructor.dotUncheckedClasses].forEach((className) => {
            this.dotTarget.classList.remove(className);
        });
    }
}
