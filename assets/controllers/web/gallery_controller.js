import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['dialog', 'image', 'caption'];

    open(event) {
        event.preventDefault();
        this.imageTarget.src = event.params.src;
        this.imageTarget.alt = event.params.alt;
        this.captionTarget.textContent = event.params.alt;
        this.dialogTarget.showModal();
    }

    close(event) {
        event?.preventDefault();

        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    clear() {
        this.imageTarget.removeAttribute('src');
    }

    backdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }
}
