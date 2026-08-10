import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['input', 'track', 'knob'];

    static values = {
        disabled: Boolean,
        variant: String,
    };

    connect() {
        this.syncVisualState();
    }

    syncVisualState() {
        const checked = this.inputTarget.checked;
        const disabled = this.disabledValue || this.inputTarget.disabled;
        const isBrandVariant = this.variantValue === 'brand';

        this.resetTrackClasses();
        this.resetKnobClasses();

        if (disabled) {
            this.trackTarget.classList.add(
                ...(isBrandVariant
                    ? ['bg-gray-100', 'dark:bg-gray-800']
                    : ['bg-gray-100', 'dark:bg-gray-800']),
            );
            this.knobTarget.classList.add('bg-gray-50');
        } else if (isBrandVariant) {
            if (checked) {
                this.trackTarget.classList.add('bg-brand-500', 'dark:bg-brand-500');
            } else {
                this.trackTarget.classList.add('bg-gray-200', 'dark:bg-white/10');
            }

            this.knobTarget.classList.add('bg-white');
        } else {
            this.trackTarget.classList.add('bg-gray-700', 'dark:bg-white/10');
            this.knobTarget.classList.add('bg-white');
        }

        this.knobTarget.classList.toggle('translate-x-full', checked);
        this.knobTarget.classList.toggle('translate-x-0', !checked);
        this.inputTarget.setAttribute('aria-checked', checked ? 'true' : 'false');
    }

    resetTrackClasses() {
        [
            'bg-brand-500',
            'dark:bg-brand-500',
            'bg-gray-200',
            'dark:bg-white/10',
            'bg-gray-100',
            'dark:bg-gray-800',
            'bg-gray-700',
        ].forEach((className) => this.trackTarget.classList.remove(className));
    }

    resetKnobClasses() {
        ['bg-white', 'bg-gray-50', 'translate-x-full', 'translate-x-0'].forEach((className) => {
            this.knobTarget.classList.remove(className);
        });
    }
}
