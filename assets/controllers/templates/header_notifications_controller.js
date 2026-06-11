import { Controller } from '@hotwired/stimulus';
import { useClickOutside } from "stimulus-use";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        notificationsDropdownOpen: Boolean,
        activeNotifications: Boolean,
    };

    // define classes keys (real class defined in the HTML template)
    static classes = ['hide'];

    static targets = [
        'notificationsDropdown',
    ];

    initialize() {
        this.notificationsDropdownOpenValue = false;
        this.activeNotificationsValue = true;
    }

    connect() {
        // this enables the automatic event 'click:outside'
        useClickOutside(this);
    }

    disconnect() {
    }

    /**
     * Created to avoid the data-action "click:outside" in the HTML
     */
    clickOutside(event) {
        this.closeNotificationsDropdown();
    }

    closeNotificationsDropdown() {
        this.notificationsDropdownOpenValue = false;
    }

    toggleNotificationsDropdown() {
        this.notificationsDropdownOpenValue = !this.notificationsDropdownOpenValue;
        this.activeNotificationsValue = false;
    }

    notificationsDropdownOpenValueChanged(newValue) {
        if (this.hasNotificationsDropdownTarget) {
            if (newValue) {
                this.notificationsDropdownTarget.classList.remove(this.hideClass);
            } else {
                this.notificationsDropdownTarget.classList.add(this.hideClass);
            }
        }
    }

    activeNotificationsValueChanged(newValue) {
        this.element.setAttribute("data-notifications-status", newValue ? "unread" : "read");
    }
}
