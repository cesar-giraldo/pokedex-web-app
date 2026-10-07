import { Controller } from '@hotwired/stimulus';
import 'trix';
import 'trix/dist/trix.min.css';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['input'];

    static values = {
        disabled: Boolean,
    };

    connect() {
        if (this.disabledValue) {
            this.element.querySelector('trix-editor')?.setAttribute('contenteditable', 'false');
        }
    }
}
