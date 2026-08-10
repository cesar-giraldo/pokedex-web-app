import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['select'];

    connect() {
        this.updateSelectedState();
    }

    onChange() {
        this.updateSelectedState();
    }

    updateSelectedState() {
        const hasValue = this.selectTarget.value !== '';

        this.selectTarget.classList.toggle('text-gray-800', hasValue);
        this.selectTarget.classList.toggle('dark:text-white/90', hasValue);
        this.selectTarget.classList.toggle('text-gray-400', !hasValue);
        this.selectTarget.classList.toggle('dark:text-white/30', !hasValue);
    }
}
