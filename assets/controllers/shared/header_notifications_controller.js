import { Controller } from '@hotwired/stimulus';
import { useClickOutside } from 'stimulus-use';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        notificationsDropdownOpen: Boolean,
    };

    static classes = ['hide'];

    static targets = [
        'notificationsDropdown',
        'toggle',
    ];

    initialize() {
        this.notificationsDropdownOpenValue = false;
    }

    connect() {
        useClickOutside(this);
        this.onKeydown = (event) => {
            if ('Escape' !== event.key || !this.notificationsDropdownOpenValue) {
                return;
            }

            this.closeNotificationsDropdown();
        };
        window.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        window.removeEventListener('keydown', this.onKeydown);
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
        if (this.hasToggleTarget) {
            this.toggleTarget.setAttribute('aria-expanded', newValue ? 'true' : 'false');
        }

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
