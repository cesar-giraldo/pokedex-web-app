const STORAGE_KEY = 'admin_success_notifications_html';

/**
 * Preserva en sessionStorage las alertas de éxito renderizadas en el DOM
 * antes de un redirect del paginador que consumiría el flash de Symfony.
 */
export function persistSuccessNotificationsFromDom() {
    const nodes = document.querySelectorAll('[data-admin-success-notification]');

    if (0 === nodes.length) {
        return;
    }

    const snapshots = [...nodes].map((node) => node.outerHTML);

    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(snapshots));
}

/**
 * @return {string[]}
 */
export function consumePendingSuccessNotificationHtml() {
    const raw = sessionStorage.getItem(STORAGE_KEY);

    if (null === raw) {
        return [];
    }

    sessionStorage.removeItem(STORAGE_KEY);

    try {
        const snapshots = JSON.parse(raw);

        if (!Array.isArray(snapshots)) {
            return [];
        }

        return snapshots.filter((snapshot) => 'string' === typeof snapshot && '' !== snapshot);
    } catch {
        return [];
    }
}
