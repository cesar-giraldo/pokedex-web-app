import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['dialog', 'loginPanel', 'profilePanel', 'cancel'];

    connect() {
        this.pokemonId = '';
        this.cookieName = '';
        this.destination = '';
    }

    open(mode, pokemonId, cookieName, destination) {
        this.pokemonId = String(pokemonId);
        this.cookieName = cookieName;
        this.destination = destination;
        this.loginPanelTarget.hidden = mode !== 'login';
        this.profilePanelTarget.hidden = mode !== 'profile';
        this.dialogTarget.setAttribute(
            'aria-labelledby',
            mode === 'profile' ? 'favorite-dialog-profile-message' : 'favorite-dialog-login-message',
        );

        if (!this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }

        const cancel = this.cancelTargets.find((button) => button.closest('[hidden]') === null);
        cancel?.focus();
    }

    confirm(event) {
        event.preventDefault();
        this.writeCookie();
        window.location.assign(this.destination);
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

    writeCookie() {
        if (!/^[1-9]\d*$/.test(this.pokemonId) || this.cookieName === '') {
            return;
        }

        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${this.cookieName}=${this.pokemonId}; Path=/; Max-Age=86400; SameSite=Lax${secure}`;
    }
}
