import { Controller } from '@hotwired/stimulus';
import { useClickOutside } from "stimulus-use";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        notificationsDropdownOpen: Boolean,
        markAllUrl: String,
        csrfToken: String,
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

        if (this.notificationsDropdownOpenValue) {
            this.markAllAsRead();
        }
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

    async markAllAsRead() {
        if (!this.hasMarkAllUrlValue || this.element.getAttribute('data-notifications-status') !== 'unread') {
            return;
        }

        const body = new URLSearchParams();
        body.set('_token', this.csrfTokenValue);

        const response = await fetch(this.markAllUrlValue, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body: body.toString(),
        });

        if (response.ok) {
            this.element.setAttribute('data-notifications-status', 'read');
        }
    }
}
