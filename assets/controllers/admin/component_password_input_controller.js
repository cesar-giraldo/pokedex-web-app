import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['input', 'toggle', 'showIcon', 'hideIcon'];

    static values = {
        disabled: Boolean,
    };

    toggleVisibility(event) {
        event.preventDefault();

        if (this.disabledValue) {
            return;
        }

        const isHidden = this.inputTarget.type === 'password';

        this.inputTarget.type = isHidden ? 'text' : 'password';
        this.showIconTarget.classList.toggle('hidden', isHidden);
        this.hideIconTarget.classList.toggle('hidden', !isHidden);
        this.toggleTarget.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        this.toggleTarget.setAttribute('aria-label', isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
    }
}
