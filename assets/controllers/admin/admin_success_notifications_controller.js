import { Controller } from '@hotwired/stimulus';
import { consumePendingSuccessNotificationHtml } from '../../admin/utils/success_notifications_storage.js';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    connect() {
        this.restorePendingNotifications();
    }

    restorePendingNotifications() {
        const snapshots = consumePendingSuccessNotificationHtml();

        if (0 === snapshots.length) {
            return;
        }

        this.element.classList.remove('hidden');

        snapshots.forEach((html) => {
            this.element.insertAdjacentHTML('beforeend', html);
        });

        this.application.load({ element: this.element });
    }
}
