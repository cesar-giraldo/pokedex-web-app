import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['dialog'];

    open(event) {
        event.preventDefault();

        if (!this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }

        const field = this.dialogTarget.querySelector('input[name="current_password"]');
        const cancel = this.dialogTarget.querySelector('[data-action="account-deactivation-dialog#cancel"]');
        const focusTarget = field ?? cancel;

        if (focusTarget instanceof HTMLElement) {
            focusTarget.focus();
        }
    }

    cancel(event) {
        event.preventDefault();
        this.close();
    }

    backdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    close() {
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }
}
