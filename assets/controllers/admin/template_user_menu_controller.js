import { Controller } from '@hotwired/stimulus';
import { useClickOutside } from "stimulus-use";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        userMenuDropdownOpen: Boolean
    };

    // define classes keys (real class defined in the HTML template)
    static classes = ['hide'];

    static targets = [
        'userMenuDropdown'
    ];

    initialize() {
        this.userMenuDropdownOpenValue = false;
    }

    connect() {
        // this enables the automatic event 'click:outside'
        useClickOutside(this);
    }

    disconnect() {
    }

    // Created to fix the the data-action "click:outside" event
    clickOutside(event) {
        this.closeUserMenuDropdown();
    }
    closeUserMenuDropdown() {
        this.userMenuDropdownOpenValue = false;
    }
    toggleUserMenuDropdown(event) {
        event.preventDefault();
        this.userMenuDropdownOpenValue = !this.userMenuDropdownOpenValue;
    }
    userMenuDropdownOpenValueChanged(newValue) {
        if (this.hasUserMenuDropdownTarget) {
            if (newValue) {
                this.userMenuDropdownTarget.classList.remove(this.hideClass);
            } else {
                this.userMenuDropdownTarget.classList.add(this.hideClass);
            }
        }
        this.element.setAttribute("data-user-dropdown-status", newValue ? "open" : "closed");
    }
}
