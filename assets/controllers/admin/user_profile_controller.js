import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        openInfo: Boolean,
        openPassword: Boolean,
    };

    static targets = [
        'profileAddressModal',
        'profileInfoModal',
        'profilePasswordModal',
    ];

    initialize() {
        this.openInfoValue = false;
        this.openPasswordValue = false;
    }

    connect() {
        if (this.openInfoValue) {
            this.openInfoModal();
        }

        if (this.openPasswordValue) {
            this.openPasswordModal();
        }
    }

    toggleInfoModal(event) {
        event.preventDefault();
        this.openInfoValue = !this.openInfoValue;
    }

    togglePasswordModal(event) {
        event.preventDefault();
        this.openPasswordValue = !this.openPasswordValue;
    }

    openInfoModal() {
        this.openInfoValue = true;
    }

    openPasswordModal() {
        this.openPasswordValue = true;
    }

    openInfoValueChanged(newValue) {
        if (this.hasProfileInfoModalTarget) {
            this.updateModalVisibility(this.profileInfoModalTarget, newValue);
        }
    }

    openPasswordValueChanged(newValue) {
        if (this.hasProfilePasswordModalTarget) {
            this.updateModalVisibility(this.profilePasswordModalTarget, newValue);
        }
    }

    updateModalVisibility(modalElement, isVisible) {
        modalElement.classList.toggle('flex', isVisible);
        modalElement.classList.toggle('hidden', !isVisible);
    }
}
