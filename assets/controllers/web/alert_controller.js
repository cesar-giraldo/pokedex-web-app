import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'eager' */
export default class extends Controller {
    dismiss() {
        this.element.remove();
    }
}
