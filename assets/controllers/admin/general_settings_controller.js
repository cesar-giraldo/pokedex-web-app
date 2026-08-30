import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        editGeneral: Boolean,
        editLanguage: Boolean,
        showHiddenUsers: Boolean,
        enabledLanguages: Array,
        defaultLanguage: String,
    };

    static targets = [
        'generalView',
        'generalEdit',
        'generalEditButton',
        'languageView',
        'languageEdit',
        'languageEditButton',
    ];

    connect() {
        this.syncGeneralSection();
        this.syncLanguageSection();
    }

    startGeneralEdit(event) {
        event.preventDefault();
        this.editGeneralValue = true;
    }

    cancelGeneralEdit(event) {
        event.preventDefault();
        this.resetGeneralForm();
        this.editGeneralValue = false;
    }

    startLanguageEdit(event) {
        event.preventDefault();
        this.editLanguageValue = true;
    }

    cancelLanguageEdit(event) {
        event.preventDefault();
        this.resetLanguageForm();
        this.editLanguageValue = false;
    }

    editGeneralValueChanged() {
        this.syncGeneralSection();
    }

    editLanguageValueChanged() {
        this.syncLanguageSection();
    }

    syncGeneralSection() {
        this.toggleSection(
            this.editGeneralValue,
            this.hasGeneralViewTarget ? this.generalViewTarget : null,
            this.hasGeneralEditTarget ? this.generalEditTarget : null,
            this.hasGeneralEditButtonTarget ? this.generalEditButtonTarget : null,
        );
    }

    syncLanguageSection() {
        this.toggleSection(
            this.editLanguageValue,
            this.hasLanguageViewTarget ? this.languageViewTarget : null,
            this.hasLanguageEditTarget ? this.languageEditTarget : null,
            this.hasLanguageEditButtonTarget ? this.languageEditButtonTarget : null,
        );
    }

    toggleSection(isEditing, viewTarget, editTarget, editButtonTarget) {
        if (viewTarget) {
            viewTarget.classList.toggle('hidden', isEditing);
        }

        if (editTarget) {
            editTarget.classList.toggle('hidden', !isEditing);
        }

        if (editButtonTarget) {
            editButtonTarget.classList.toggle('hidden', isEditing);
        }
    }

    resetGeneralForm() {
        if (!this.hasGeneralEditTarget) {
            return;
        }

        const form = this.generalEditTarget.querySelector('form');
        if (!form) {
            return;
        }

        this.clearFormErrors(this.generalEditTarget);

        const checkbox = form.querySelector('[data-component-toggle-switch-target="input"]');
        if (checkbox instanceof HTMLInputElement) {
            checkbox.checked = this.showHiddenUsersValue;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    resetLanguageForm() {
        if (!this.hasLanguageEditTarget) {
            return;
        }

        const form = this.languageEditTarget.querySelector('form');
        if (!form) {
            return;
        }

        this.clearFormErrors(this.languageEditTarget);

        const select = form.querySelector('[data-component-single-select-target="select"]');
        if (select instanceof HTMLSelectElement) {
            select.value = this.defaultLanguageValue;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        const multiSelect = form.querySelector('[data-controller~="component-multi-select"]');
        this.resetMultiSelect(multiSelect, this.enabledLanguagesValue);
    }

    resetMultiSelect(element, values) {
        if (!(element instanceof HTMLElement)) {
            return;
        }

        const controller = this.application.getControllerForElementAndIdentifier(
            element,
            'component-multi-select',
        );

        if (!controller) {
            return;
        }

        if (controller.isOpen) {
            controller.close();
        }

        const selectedValues = Array.isArray(values) ? [...values] : [];

        controller.optionRowTargets.forEach((row) => {
            controller.markOptionSelected(row.dataset.value, false);
        });

        controller.selectedValues = selectedValues;
        selectedValues.forEach((value) => controller.markOptionSelected(value, true));
        controller.renderTags();
        controller.updateHiddenInputs();

        if (controller.hasSelectTarget) {
            Array.from(controller.selectTarget.options).forEach((option) => {
                option.selected = selectedValues.includes(option.value);
            });
        }
    }

    clearFormErrors(container) {
        container.querySelectorAll('[role="alert"]').forEach((element) => element.remove());

        container.querySelectorAll('[aria-invalid="true"]').forEach((element) => {
            element.removeAttribute('aria-invalid');
            element.removeAttribute('aria-describedby');
        });
    }
}
