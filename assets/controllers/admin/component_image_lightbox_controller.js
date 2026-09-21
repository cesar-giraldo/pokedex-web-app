/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item', 'dialog', 'image', 'caption', 'previousButton', 'nextButton', 'closeButton'];

    static values = {
        open: { type: Boolean, default: false },
    };

    connect() {
        this.currentIndex = 0;
        this.pointerStart = null;
        this.lastTrigger = null;
    }

    disconnect() {
        this.openValue = false;
        document.documentElement.classList.remove('overflow-hidden');
    }

    openValueChanged(isOpen) {
        if (!this.hasDialogTarget) {
            return;
        }

        this.dialogTarget.hidden = !isOpen;
        this.dialogTarget.classList.toggle('hidden', !isOpen);
        this.dialogTarget.classList.toggle('flex', isOpen);
        this.dialogTarget.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        document.documentElement.classList.toggle('overflow-hidden', isOpen);

        if (isOpen && this.hasCloseButtonTarget) {
            this.closeButtonTarget.focus();
        } else if (!isOpen && this.lastTrigger instanceof HTMLElement) {
            this.lastTrigger.focus();
        }
    }

    markPointer(event) {
        this.pointerStart = { x: event.clientX, y: event.clientY };
    }

    open(event) {
        event.preventDefault();

        if (this.wasDrag(event)) {
            return;
        }

        const item = event.currentTarget;
        const index = this.itemTargets.indexOf(item);
        if (index < 0) {
            return;
        }

        this.lastTrigger = item;
        this.currentIndex = index;
        this.showCurrent();
        this.openValue = true;
    }

    close(event) {
        event?.preventDefault();

        if (!this.openValue) {
            return;
        }

        this.openValue = false;
        if (this.hasImageTarget) {
            this.imageTarget.removeAttribute('src');
        }
    }

    previous(event) {
        if (!this.openValue || this.itemTargets.length < 2) {
            return;
        }

        event?.preventDefault();
        this.currentIndex = (this.currentIndex - 1 + this.itemTargets.length) % this.itemTargets.length;
        this.showCurrent();
    }

    next(event) {
        if (!this.openValue || this.itemTargets.length < 2) {
            return;
        }

        event?.preventDefault();
        this.currentIndex = (this.currentIndex + 1) % this.itemTargets.length;
        this.showCurrent();
    }

    showCurrent() {
        const item = this.itemTargets[this.currentIndex];
        if (!(item instanceof HTMLElement)) {
            return;
        }

        const src = item.dataset.componentImageLightboxSrcParam ?? '';
        const caption = item.dataset.componentImageLightboxCaptionParam ?? '';

        if (this.hasImageTarget) {
            this.imageTarget.src = src;
            this.imageTarget.alt = caption;
        }

        if (this.hasCaptionTarget) {
            this.captionTarget.textContent = caption;
            this.captionTarget.hidden = caption === '';
        }

        const showNav = this.itemTargets.length > 1;
        if (this.hasPreviousButtonTarget) {
            this.previousButtonTarget.classList.toggle('hidden', !showNav);
            this.previousButtonTarget.toggleAttribute('hidden', !showNav);
        }
        if (this.hasNextButtonTarget) {
            this.nextButtonTarget.classList.toggle('hidden', !showNav);
            this.nextButtonTarget.toggleAttribute('hidden', !showNav);
        }
    }

    wasDrag(event) {
        if (this.pointerStart === null) {
            return false;
        }

        const dx = Math.abs(event.clientX - this.pointerStart.x);
        const dy = Math.abs(event.clientY - this.pointerStart.y);
        this.pointerStart = null;

        return dx > 8 || dy > 8;
    }
}
