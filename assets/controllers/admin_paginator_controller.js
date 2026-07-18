import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    static values = {
        entity: String
    }

    initialize() {
    }

    connect() {
        const url = new URL(window.location.href);
        let currentUrlLimit = url.searchParams.get('limit');
        let currentUrlSort = url.searchParams.get('sort');
        let currentUrlDirection = url.searchParams.get('direction');
        
        let shouldRedirect = false;

        // 1. Persistence of the Record Limit argument
        const limitKey = `table_limit_${this.entityValue}`;
        const savedLimit = localStorage.getItem(limitKey);
        if (savedLimit && savedLimit !== currentUrlLimit) {
            url.searchParams.set('limit', savedLimit);
            shouldRedirect = true;
        }

        // 2. Persistence of the Ordering argument
        const sortKey = `table_sort_${this.entityValue}`;
        const savedSort = localStorage.getItem(sortKey);
        if (savedSort && savedSort !== currentUrlSort) {
            url.searchParams.set('sort', savedSort);
            shouldRedirect = true;
        }

        // 3. Persistence de la Direction argument
        const directionKey = `table_direction_${this.entityValue}`;
        const savedDirection = localStorage.getItem(directionKey);
        if (savedDirection && savedDirection !== currentUrlDirection) {
            url.searchParams.set('direction', savedDirection || 'desc');
            shouldRedirect = true;
        }

        // 4. If anything changed regarding the initial URL, we redirect exactly once.
        if (shouldRedirect) {
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
    }

    disconnect() {
    }

    updateTableLimit(event) {
        const limitValue = event.target.value;
        const storageKey = `table_limit_${this.entityValue}`;

        // We save the new preference to LocalStorage before redirecting.
        localStorage.setItem(storageKey, limitValue);

        const url = new URL(window.location.href);
        url.searchParams.set('limit', limitValue);
        url.searchParams.set('page', '1');

        window.location.href = url.toString();
    }

    /**
     * Captures the table header click, saves the sort order, and redirects.
     */
    sortColumn(event) {
        // We prevent the link's native navigation.
        event.preventDefault();
        
        // We retrieve the parameters injected into the HTML dataset.
        const column = event.currentTarget.dataset.column;
        const nextDirection = event.currentTarget.dataset.direction;

        // We save preferences in local storage.
        localStorage.setItem(`table_sort_${this.entityValue}`, column);
        localStorage.setItem(`table_direction_${this.entityValue}`, nextDirection);

        const url = new URL(window.location.href);
        url.searchParams.set('sort', column);
        url.searchParams.set('direction', nextDirection);
        url.searchParams.set('page', '1');
        
        window.location.href = url.toString();
    }
}
