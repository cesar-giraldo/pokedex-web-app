import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static outlets = ['favorite-dialog'];

    static targets = ['heart', 'empty', 'filled'];

    static values = {
        pokemonId: Number,
        favorited: Boolean,
        gate: String,
        cookieName: String,
        csrf: String,
        url: String,
        loginUrl: String,
        profileUrl: String,
        removeCard: Boolean,
        addLabel: String,
        removeLabel: String,
    };

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();

        if (this.gateValue !== 'toggle') {
            const destination = this.gateValue === 'profile' ? this.profileUrlValue : this.loginUrlValue;
            this.favoriteDialogOutlet.open(this.gateValue, this.pokemonIdValue, this.cookieNameValue, destination);

            return;
        }

        if (this.busy) {
            return;
        }

        const previous = this.favoritedValue;
        const next = !previous;
        this.busy = true;
        this.render(next, true);

        this.persist(next)
            .then((favorited) => {
                this.render(favorited, favorited !== next);

                if (!favorited && this.removeCardValue) {
                    this.removeCard();
                }
            })
            .catch(() => {
                this.render(previous, true);
            })
            .finally(() => {
                this.busy = false;
            });
    }

    async persist(favorited) {
        const response = await fetch(this.urlValue, {
            method: favorited ? 'POST' : 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': this.csrfValue,
            },
        });

        if (!response.ok) {
            throw new Error('Favorite request failed.');
        }

        const payload = await response.json();

        return payload.favorited === true;
    }

    render(favorited, animate) {
        this.favoritedValue = favorited;
        this.emptyTarget.hidden = favorited;
        this.filledTarget.hidden = !favorited;
        const label = favorited ? this.removeLabelValue : this.addLabelValue;
        this.element.setAttribute('aria-pressed', favorited ? 'true' : 'false');
        this.element.setAttribute('aria-label', label);
        this.element.setAttribute('title', label);

        if (!animate) {
            return;
        }

        this.heartTarget.classList.remove('is-pop');
        void this.heartTarget.offsetWidth;
        this.heartTarget.classList.add('is-pop');
    }

    removeCard() {
        const card = this.element.closest('li');
        const grid = this.element.closest('[data-catalog-grid]');
        card?.remove();

        const results = document.querySelector('[data-favorite-results]');

        if (!(results instanceof HTMLElement)) {
            return;
        }

        const next = Math.max(0, Number(results.dataset.favoriteResultsCount || '0') - 1);
        results.dataset.favoriteResultsCount = String(next);
        const labels = JSON.parse(results.dataset.favoriteResultsLabels || '{}');

        if (next <= 0) {
            results.textContent = labels.zero ?? '';
            grid?.setAttribute('hidden', '');
            document.querySelector('[data-catalog-empty]')?.removeAttribute('hidden');

            return;
        }

        results.textContent = next === 1
            ? (labels.one ?? '')
            : String(labels.other ?? '').replace('2', String(next));
    }
}
