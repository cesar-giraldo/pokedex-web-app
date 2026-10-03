import { Controller } from '@hotwired/stimulus';
import { useClickOutside } from "stimulus-use";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        notificationsDropdownOpen: Boolean,
    };

    static classes = ['hide'];

    static targets = [
        'notificationsDropdown',
    ];

    initialize() {
        this.notificationsDropdownOpenValue = false;
    }

    connect() {
        useClickOutside(this);
    }

    clickOutside() {
        this.closeNotificationsDropdown();
    }

    closeNotificationsDropdown() {
        this.notificationsDropdownOpenValue = false;
    }

    toggleNotificationsDropdown() {
        this.notificationsDropdownOpenValue = !this.notificationsDropdownOpenValue;
    }

    notificationsDropdownOpenValueChanged(newValue) {
        if (!this.hasNotificationsDropdownTarget) {
            return;
        }

        if (newValue) {
            this.notificationsDropdownTarget.classList.remove(this.hideClass);
        } else {
            this.notificationsDropdownTarget.classList.add(this.hideClass);
        }
    }
}
