import { Controller } from '@hotwired/stimulus';

const DISMISS_DURATION_MS = 300;

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
        if (this.isDismissing) {
            return;
        }

        this.isDismissing = true;
        this.clearAutoHideTimeout();

        const element = this.element;
        const computedStyle = window.getComputedStyle(element);
        const height = element.getBoundingClientRect().height;
        const marginTop = parseFloat(computedStyle.marginTop) || 0;

        element.style.boxSizing = 'border-box';
        element.style.height = `${height}px`;
        element.style.marginTop = `${marginTop}px`;
        element.style.overflow = 'hidden';
        element.style.pointerEvents = 'none';
        element.style.transition = [
            `height ${DISMISS_DURATION_MS}ms ease-in-out`,
            `margin-top ${DISMISS_DURATION_MS}ms ease-in-out`,
            `padding ${DISMISS_DURATION_MS}ms ease-in-out`,
            `border-width ${DISMISS_DURATION_MS}ms ease-in-out`,
            `opacity ${DISMISS_DURATION_MS}ms ease-in-out`,
        ].join(', ');

        requestAnimationFrame(() => {
            element.style.height = '0';
            element.style.marginTop = '0';
            element.style.paddingTop = '0';
            element.style.paddingBottom = '0';
            element.style.paddingLeft = '0';
            element.style.paddingRight = '0';
            element.style.borderWidth = '0';
            element.style.opacity = '0';
        });

        window.setTimeout(() => {
            const container = element.parentElement;
            element.remove();

            if (container && 0 === container.children.length) {
                container.classList.add('hidden');
            }
        }, DISMISS_DURATION_MS);
    }

    clearAutoHideTimeout() {
        if (undefined !== this.autoHideTimeoutId) {
            window.clearTimeout(this.autoHideTimeoutId);
            this.autoHideTimeoutId = undefined;
        }
    }
}
