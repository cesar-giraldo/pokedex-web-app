/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['title', 'message'];

    static values = {
        open: { type: Boolean, default: false },
    };

    openValueChanged(isOpen) {
        this.element.hidden = !isOpen;
        this.element.classList.toggle('hidden', !isOpen);
        this.element.classList.toggle('flex', isOpen);
        this.element.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    }

    open(event) {
        const detail = event?.detail ?? event ?? {};

        if (this.hasTitleTarget && detail.title) {
            this.titleTarget.textContent = detail.title;
        }

        if (this.hasMessageTarget && detail.message) {
            this.messageTarget.textContent = detail.message;
        }

        this.openValue = true;
    }

    cancel(event) {
        event?.preventDefault();

        if (!this.openValue) {
            return;
        }

        this.openValue = false;
        this.dispatch('cancelled');
    }

    confirm(event) {
        event?.preventDefault();

        if (!this.openValue) {
            return;
        }

        this.openValue = false;
        this.dispatch('confirmed');
    }
}
