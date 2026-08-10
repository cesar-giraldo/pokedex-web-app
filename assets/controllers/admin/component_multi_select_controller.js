import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = [
        'select',
        'hiddenInputsContainer',
        'hiddenInput',
        'menu',
        'arrow',
        'tagsContainer',
        'placeholder',
        'optionRow',
        'tagTemplate',
        'trigger',
    ];

    static values = {
        inputName: String,
        disabled: Boolean,
        maxSelections: Number,
    };

    connect() {
        this.isOpen = false;
        this.options = [];
        this.selectedValues = [];

        this.loadOptions();
        this.syncFromHiddenInputs();
        this.applyInitialSelection();
        this.renderTags();
        this.updateHiddenInputs();
    }

    loadOptions() {
        if (!this.hasSelectTarget) {
            return;
        }

        this.options = Array.from(this.selectTarget.options).map((option) => ({
            value: option.value,
            text: option.text,
        }));

        this.selectedValues = Array.from(this.selectTarget.options)
            .filter((option) => option.selected)
            .map((option) => option.value);
    }

    syncFromHiddenInputs() {
        if (!this.hasHiddenInputTarget || this.hiddenInputTargets.length === 0) {
            return;
        }

        this.selectedValues = this.hiddenInputTargets.map((input) => input.value);
    }

    applyInitialSelection() {
        this.selectedValues.forEach((value) => this.markOptionSelected(value, true));
    }

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();

        if (this.disabledValue) {
            return;
        }

        this.isOpen ? this.close() : this.open();
    }

    open() {
        if (this.disabledValue) {
            return;
        }

        this.closeOtherInstances();

        this.isOpen = true;
        this.element.classList.add('z-50');
        this.menuTarget.classList.remove('hidden');
        this.arrowTarget.classList.add('rotate-180');
        this.setAriaExpanded(true);
    }

    close(event) {
        if (event && this.element.contains(event.target)) {
            return;
        }

        this.isOpen = false;
        this.element.classList.remove('z-50');
        this.menuTarget.classList.add('hidden');
        this.arrowTarget.classList.remove('rotate-180');
        this.setAriaExpanded(false);
    }

    closeOtherInstances() {
        this.element
            .closest('body')
            ?.querySelectorAll('[data-controller~="component-multi-select"]')
            .forEach((element) => {
                if (element === this.element) {
                    return;
                }

                const controller = this.application.getControllerForElementAndIdentifier(
                    element,
                    'component-multi-select',
                );

                if (controller?.isOpen) {
                    controller.close();
                }
            });
    }

    onWindowKeydown(event) {
        if (event.key === 'Escape' && this.isOpen) {
            event.preventDefault();
            this.close();
            this.triggerTarget.focus();
        }
    }

    onTriggerKeydown(event) {
        if (this.disabledValue) {
            return;
        }

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            this.toggle(event);
        }

        if (event.key === 'ArrowDown' && !this.isOpen) {
            event.preventDefault();
            this.open();
        }
    }

    select(event) {
        event.stopPropagation();

        if (this.disabledValue) {
            return;
        }

        const value = event.currentTarget.dataset.value;
        const isSelected = this.selectedValues.includes(value);

        if (!isSelected && this.hasMaxSelectionsValue && this.maxSelectionsValue > 0 && this.selectedValues.length >= this.maxSelectionsValue) {
            return;
        }

        if (isSelected) {
            this.selectedValues = this.selectedValues.filter((selectedValue) => selectedValue !== value);
            this.markOptionSelected(value, false);
        } else {
            this.selectedValues.push(value);
            this.markOptionSelected(value, true);
        }

        this.renderTags();
        this.updateHiddenInputs();
    }

    remove(event) {
        event.preventDefault();
        event.stopPropagation();

        if (this.disabledValue) {
            return;
        }

        const selectedIndex = parseInt(event.currentTarget.dataset.selectedIndex, 10);
        const value = this.selectedValues[selectedIndex];

        if (value === undefined) {
            return;
        }

        this.selectedValues.splice(selectedIndex, 1);
        this.markOptionSelected(value, false);
        this.renderTags();
        this.updateHiddenInputs();
    }

    renderTags() {
        this.tagsContainerTarget.querySelectorAll('[data-component-multi-select-target="tag"]').forEach((tag) => tag.remove());

        if (this.selectedValues.length === 0) {
            this.placeholderTarget.classList.remove('hidden');
            return;
        }

        this.placeholderTarget.classList.add('hidden');

        this.selectedValues.forEach((value, index) => {
            const option = this.options.find((item) => item.value === value);
            if (!option) {
                return;
            }

            const tag = this.createTagElement(option.text, index);
            this.tagsContainerTarget.insertBefore(tag, this.placeholderTarget);
        });
    }

    createTagElement(text, selectedIndex) {
        const tag = this.tagTemplateTarget.content.firstElementChild.cloneNode(true);

        tag.dataset.componentMultiSelectTarget = 'tag';
        tag.querySelector('[data-component-multi-select-target="tagLabel"]').textContent = text;

        const removeButton = tag.querySelector('[data-action*="remove"]');
        removeButton.dataset.selectedIndex = String(selectedIndex);

        if (this.disabledValue) {
            removeButton.classList.add('hidden');
        }

        return tag;
    }

    updateHiddenInputs() {
        const container = this.hiddenInputsContainerTarget;
        container.innerHTML = '';

        this.selectedValues.forEach((value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = this.inputNameValue;
            input.value = value;
            input.dataset.componentMultiSelectTarget = 'hiddenInput';
            container.appendChild(input);
        });

        container.dispatchEvent(new Event('change', { bubbles: true }));
    }

    markOptionSelected(value, selected) {
        const row = this.getOptionRow(value);
        const option = row?.closest('[role="option"]');

        if (!row || !option) {
            return;
        }

        row.classList.toggle('border-l-brand-500', selected);
        row.classList.toggle('bg-brand-50', selected);
        row.classList.toggle('dark:bg-brand-500/10', selected);
        option.setAttribute('aria-selected', selected ? 'true' : 'false');
    }

    getOptionRow(value) {
        return this.optionRowTargets.find((row) => row.dataset.value === value);
    }

    setAriaExpanded(expanded) {
        if (!this.hasTriggerTarget) {
            return;
        }

        this.triggerTarget.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
}
