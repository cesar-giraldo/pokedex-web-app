import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'eager' */
export default class extends Controller {
    static targets = ['input'];

    connect() {
        this.sync();
    }

    sync() {
        this.inputTarget.setAttribute('aria-checked', this.inputTarget.checked ? 'true' : 'false');
    }
}
