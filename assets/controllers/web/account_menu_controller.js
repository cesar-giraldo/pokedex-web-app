import { Controller } from '@hotwired/stimulus';
import { useClickOutside } from 'stimulus-use';

/* stimulusFetch: 'eager' */
export default class extends Controller {
    connect() {
        useClickOutside(this);
    }

    clickOutside() {
        this.element.open = false;
    }
}
