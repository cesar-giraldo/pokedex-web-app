import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['input', 'trigger'];

    static values = {
        disabled: Boolean,
    };

    openPicker(event) {
        if (this.disabledValue) {
            return;
        }

        event.preventDefault();

        if (typeof this.inputTarget.showPicker === 'function') {
            this.inputTarget.showPicker();
        } else {
            this.inputTarget.focus();
        }
    }
}
