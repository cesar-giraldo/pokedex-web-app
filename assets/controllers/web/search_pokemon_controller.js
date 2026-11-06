import { Controller } from '@hotwired/stimulus';

/*
* The following line makes this controller "lazy": it won't be downloaded until needed
* See https://symfony.com/bundles/StimulusBundle/current/index.html#lazy-stimulus-controllers
*/

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['pokemonName', 'pokemonDetails', 'spinner']

    initialize() {
    }

    connect() {
        // add or remove classes, attributes, dispatch custom events, etc.
        // this.fooTarget.addEventListener('click', this._fooBar)
    }

    // Add custom controller actions here
    // fooBar() { this.fooTarget.classList.toggle(this.bazClass) }

    disconnect() {
        // Here you should remove all event listeners added in "connect()" 
        // this.fooTarget.removeEventListener('click', this._fooBar)
    }

    // External search using PokeAPI directly from the frontend
    async search() {
        const name = this.pokemonNameTarget.value.trim().toLowerCase();
        if (!name) {
            alert('Please enter the pokemon name');
            return;
        }

        this.showSpinner(true);

        try {
            const res = await fetch(`https://pokeapi.co/api/v2/pokemon/${encodeURIComponent(name)}`);
            if (!res.ok) throw new Error('Not found');
            const data = await res.json();
            this.renderPokemon(data);
        } catch (e) {
            console.error("Error: " + e.message);
            this.pokemonDetailsTarget.innerHTML = `<div class="text-red-600">Pokemon no encontrado</div>`;
        } finally {
            this.showSpinner(false);
        }
    }

    // Internal search using our Symfony backend
    async internalSearch() {
        const name = this.pokemonNameTarget.value.trim();
        if (!name) {
            alert('Please enter the pokemon name');
            return;
        }

        this.showSpinner(true);

        try {
            const res = await fetch(`/internal-pokemon-search/${encodeURIComponent(name)}`);
            if (!res.ok) throw new Error('Not found');
            const data = await res.json();

            if (!data.success) {
                throw new Error(data.errors?.[0] || 'Unknown error');
            }
            this.renderInternalPokemon(data.data);
        } catch (e) {
            console.error("Error: " + e.message);
            this.pokemonDetailsTarget.innerHTML = `<div class="text-red-600">Pokemon no encontrado</div>`;
        } finally {
            this.showSpinner(false);
        }
    }

    renderPokemon(data) {
        const name = this.escapeHtml(data.name);
        const sprite = this.escapeHtml(data.sprites?.front_default ?? '');
        const hp = this.escapeHtml(data.stats.find(s => s.stat.name === 'hp')?.base_stat ?? '-');

        this.pokemonDetailsTarget.innerHTML = `
            <div class="flex items-center gap-4">
                <img src="${sprite}" alt="${name}" class="w-20 h-20">
                <div>
                    <h3 class="text-xl font-bold">${name}</h3>
                    <div>HP: ${hp} </div>
                </div>
            </div>`;
    }

    renderInternalPokemon(data) {
        const name = this.escapeHtml(data.name);
        const sprite = this.escapeHtml(data.spriteFront ?? '');
        const hp = this.escapeHtml(data.healthPoints ?? '-');

        this.pokemonDetailsTarget.innerHTML = `
            <div class="flex items-center gap-4">
                <img src="${sprite}" alt="${name}" class="w-20 h-20">
                <div>
                    <h3 class="text-xl font-bold">${name}</h3>
                    <div>HP: ${hp} </div>
                </div>
            </div>`;
    }

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    showSpinner(visible) {
        // Simple toggle to show/hide the spinner (.toggle, .replace can also be used)
        if (visible) {
            this.spinnerTarget.classList.remove("hidden")
        } else {
            this.spinnerTarget.classList.add('hidden');
        }
    }
}
