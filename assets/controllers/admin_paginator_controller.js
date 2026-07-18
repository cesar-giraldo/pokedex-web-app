import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {

    static values = {
        entity: String
    }

    initialize() {
    }

    connect() {
        // 1. Generate a unique key for localStorage using the entity name (ex: "table_limit_pokemon")
        const storageKey = `table_limit_${this.entityValue}`;
        const savedLimit = localStorage.getItem(storageKey);
        
        // 2. We read the limit currently present in the page URL.
        const url = new URL(window.location.href);
        const currentUrlLimit = url.searchParams.get('limit');

        // 3. If there is a saved limit and it differs from the one in the current URL, we force an automatic reload.
        if (savedLimit && savedLimit !== currentUrlLimit) {
            url.searchParams.set('limit', savedLimit);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }
    }

    disconnect() {
    }

    updateTableLimit(event) {
        const limitValue = event.target.value;
        const storageKey = `table_limit_${this.entityValue}`;

        // 4. We save the new preference to LocalStorage before redirecting.
        localStorage.setItem(storageKey, limitValue);

        const url = new URL(window.location.href);
        url.searchParams.set('limit', limitValue);
        url.searchParams.set('page', '1');

        window.location.href = url.toString();
    }
}
