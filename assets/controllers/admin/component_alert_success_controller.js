import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        autoHideDelay: { type: Number, default: 5000 },
        dismissible: { type: Boolean, default: true },
    };

    connect() {
        if (this.autoHideDelayValue > 0) {
            this.autoHideTimeoutId = window.setTimeout(() => {
                this.dismiss();
            }, this.autoHideDelayValue);
        }
    }

    disconnect() {
        this.clearAutoHideTimeout();
    }

    dismiss() {
        this.clearAutoHideTimeout();

        this.element.classList.add('opacity-0', 'pointer-events-none');

        window.setTimeout(() => {
            this.element.remove();
        }, 300);
    }

    clearAutoHideTimeout() {
        if (undefined !== this.autoHideTimeoutId) {
            window.clearTimeout(this.autoHideTimeoutId);
            this.autoHideTimeoutId = undefined;
        }
    }
}
