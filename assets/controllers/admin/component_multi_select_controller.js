import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = [
        "select",
        "hiddenInput",
        "menu",
        "arrow",
        "tagsContainer",
        "placeholder",
        "optionRow"
    ];

    connect() {
        this.isOpen = false;
        this.options = [];  // Almacena el estado de las opciones [{value, text, selected}]
        this.selected = []; // Almacena los índices seleccionados

        this.loadOptions();
    }

    // Lee las opciones desde el select HTML nativo
    loadOptions() {
        if (!this.hasSelectTarget) return;

        const htmlOptions = this.selectTarget.options;
        for (let i = 0; i < htmlOptions.length; i++) {
            this.options.push({
                value: htmlOptions[i].value,
                text: htmlOptions[i].text,
                selected: htmlOptions[i].hasAttribute('selected')
            });

            // Si la opción ya viene pre-seleccionada desde Twig
            if (htmlOptions[i].hasAttribute('selected')) {
                this.selected.push(i);
                this.optionRowTargets[i].classList.add('border-primary');
            }
        }
        this.renderTags();
    }

    toggle(event) {
        event.stopPropagation();
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.isOpen = true;
        this.menuTarget.classList.remove('hidden');
        this.arrowTarget.classList.add('rotate-180');
    }

    close(event) {
        // click.outside manual: si haces clic dentro del componente, ignora el cierre global
        if (event && this.element.contains(event.target)) return;

        this.isOpen = false;
        this.menuTarget.classList.add('hidden');
        this.arrowTarget.classList.remove('rotate-180');
    }

    select(event) {
        const index = parseInt(event.currentTarget.dataset.index);

        if (!this.options[index].selected) {
            this.options[index].selected = true;
            this.selected.push(index);
            this.optionRowTargets[index].classList.add('border-primary');
        } else {
            this.selected = this.selected.filter(i => i !== index);
            this.options[index].selected = false;
            this.optionRowTargets[index].classList.remove('border-primary');
        }

        this.renderTags();
        this.updateHiddenInput();
    }

    remove(event) {
        event.stopPropagation(); // Evita que al eliminar el badge se abra el dropdown
        const indexInSelected = parseInt(event.currentTarget.dataset.selectedIndex);
        const optionIndex = this.selected[indexInSelected];

        this.options[optionIndex].selected = false;
        this.optionRowTargets[optionIndex].classList.remove('border-primary');
        this.selected.splice(indexInSelected, 1);

        this.renderTags();
        this.updateHiddenInput();
    }

    // Genera las etiquetas de manera dinámica basándose en el estado de 'this.selected'
    renderTags() {
        // Conservamos únicamente el contenedor del input/placeholder
        const tags = this.tagsContainerTarget.querySelectorAll('.group');
        tags.forEach(tag => tag.remove());

        if (this.selected.length === 0) {
            this.placeholderTarget.classList.remove('hidden');
        } else {
            this.placeholderTarget.classList.add('hidden');

            this.selected.forEach((optionIndex, idx) => {
                const optionData = this.options[optionIndex];
                const tagHtml = this.createTagElement(optionData.text, idx);
                this.tagsContainerTarget.insertBefore(tagHtml, this.placeholderTarget);
            });
        }
    }

    // Helper para fabricar el nodo HTML de la etiqueta seleccionada
    createTagElement(text, selectedIndex) {
        const div = document.createElement('div');
        div.className = "group flex items-center justify-center rounded-full border-[0.7px] border-transparent bg-gray-100 py-1 pr-2 pl-2.5 text-sm text-gray-800 hover:border-gray-200 dark:bg-gray-800 dark:text-white/90 dark:hover:border-gray-800";

        div.innerHTML = `
            <div class="max-w-full flex-initial">${text}</div>
            <div class="flex flex-auto flex-row-reverse">
                <div class="cursor-pointer pl-2 text-gray-500 group-hover:text-gray-400 dark:text-gray-400" 
                     data-action="click->component-multi-select#remove" 
                     data-selected-index="${selectedIndex}">
                    <svg class="fill-current" role="button" width="14" height="14" viewbox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M3.40717 4.46881C3.11428 4.17591 3.11428 3.70104 3.40717 3.40815C3.70006 3.11525 4.17494 3.11525 4.46783 3.40815L6.99943 5.93975L9.53095 3.40822C9.82385 3.11533 10.2987 3.11533 10.5916 3.40822C10.8845 3.70112 10.8845 4.17599 10.5916 4.46888L8.06009 7.00041L10.5916 9.53193C10.8845 9.82482 10.8845 10.2997 10.5916 10.5926C10.2987 10.8855 9.82385 10.8855 9.53095 10.5926L6.99943 8.06107L4.46783 10.5927C4.17494 10.8856 3.70006 10.8856 3.40717 10.5927C3.11428 10.2998 3.11428 9.8249 3.40717 9.53201L5.93877 7.00041L3.40717 4.46881Z" fill=""/>
                    </svg>
                </div>
            </div>
        `;
        return div;
    }

    updateHiddenInput() {
        const values = this.selected.map(idx => this.options[idx].value);
        this.hiddenInputTarget.value = values.join(',');

        // Despachamos un evento nativo para avisar a componentes externos de Symfony (ej. Live Components o Turbo)
        this.hiddenInputTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
