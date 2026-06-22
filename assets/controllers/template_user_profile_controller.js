import { Controller } from '@hotwired/stimulus';

/*
* The following line makes this controller "lazy": it won't be downloaded until needed
* See https://symfony.com/bundles/StimulusBundle/current/index.html#lazy-stimulus-controllers
*/

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    static values = {
        openInfoModal: Boolean,
        openAddressModal: Boolean
    };

    static targets = [
        'profileAddressModal',
        'profileInfoModal'
    ];

    initialize() {
        this.openInfoModalValue = false;
        this.openAddressModalValue = false;
    }

    toggleInfoModal(event) {
        event.preventDefault();
        this.openInfoModalValue = !this.openInfoModalValue;
    }

    toggleAddressModal(event) {
        event.preventDefault();
        this.openAddressModalValue = !this.openAddressModalValue;
    }

    openInfoModalValueChanged(newValue) {
        if (this.hasProfileInfoModalTarget) {
            this.updateModalVisibility(this.profileInfoModalTarget, newValue);
        }
    }

    openAddressModalValueChanged(newValue) {
        if (this.hasProfileAddressModalTarget) {
            this.updateModalVisibility(this.profileAddressModalTarget, newValue);
        }
    }

    updateModalVisibility(modalElement, isVisible) {
        modalElement.classList.toggle('flex', isVisible);
        modalElement.classList.toggle('hidden', !isVisible);
    }
}
