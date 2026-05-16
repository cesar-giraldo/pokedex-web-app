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

    renderPokemon(data) {
        this.pokemonDetailsTarget.innerHTML = `
            <div class="flex items-center gap-4">
                <img src="${data.sprites.front_default}" alt="${data.name}" class="w-20 h-20">
                <div>
                    <h3 class="text-xl font-bold">${data.name}</h3>
                    <div>HP: ${data.stats.find(s => s.stat.name === 'hp')?.base_stat ?? '-'} </div>
                </div>
            </div>`;
    }

    showSpinner(visible) {
        // Simple toggle to show/hide the spinner (.toogle, .replace can also be used)
        if (visible) {
            this.spinnerTarget.classList.remove("hidden")
        } else {
            this.spinnerTarget.classList.add('hidden');
        }
    }
}
