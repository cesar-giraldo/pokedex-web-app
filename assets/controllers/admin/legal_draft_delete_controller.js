import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['form'];

    connect() {
        this.dialogElement = this.element.querySelector('[data-controller~="component-confirm-dialog"]');
        this.onConfirmed = () => {
            if (this.hasFormTarget) {
                this.formTarget.requestSubmit();
            }
        };

        this.dialogElement?.addEventListener('component-confirm-dialog:confirmed', this.onConfirmed);
    }

    disconnect() {
        this.dialogElement?.removeEventListener('component-confirm-dialog:confirmed', this.onConfirmed);
    }

    request(event) {
        event.preventDefault();

        const dialog = this.dialogElement
            ? this.application.getControllerForElementAndIdentifier(this.dialogElement, 'component-confirm-dialog')
            : null;

        dialog?.open();
    }
}
