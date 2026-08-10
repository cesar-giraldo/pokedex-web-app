import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['input', 'countrySelect'];

    static values = {
        countryCodes: Object,
        formatRegex: String,
        disabled: Boolean,
    };

    connect() {
        if ('' === this.inputTarget.value.trim()) {
            this.applyPrefixOnly();
        } else {
            this.formatCurrentValue();
        }
    }

    onCountryChange() {
        if (this.disabledValue) {
            return;
        }

        const prefix = this.getSelectedPrefix();
        const localDigits = this.extractLocalDigitsFromFormatted(this.inputTarget.value);

        this.inputTarget.value = this.formatPhone(localDigits, prefix);
    }

    onInput() {
        if (this.disabledValue) {
            return;
        }

        this.formatCurrentValue();
    }

    onBlur() {
        if (this.disabledValue || '' === this.inputTarget.value.trim()) {
            return;
        }

        const regex = new RegExp(this.formatRegexValue);

        if (!regex.test(this.inputTarget.value)) {
            this.inputTarget.setCustomValidity('Introduce un número de teléfono con el formato requerido.');
        } else {
            this.inputTarget.setCustomValidity('');
        }
    }

    formatCurrentValue() {
        const prefix = this.getSelectedPrefix();
        const localDigits = this.extractLocalDigits(this.inputTarget.value, prefix);

        this.inputTarget.value = this.formatPhone(localDigits, prefix);
    }

    applyPrefixOnly() {
        this.inputTarget.value = this.getSelectedPrefix();
    }

    getSelectedPrefix() {
        const country = this.countrySelectTarget.value;

        return this.countryCodesValue[country] ?? '+1';
    }

    extractLocalDigits(value, prefix) {
        const formattedDigits = this.extractLocalDigitsFromFormatted(value);

        if ('' !== formattedDigits) {
            return formattedDigits;
        }

        const allDigits = value.replace(/\D/g, '');
        const prefixDigits = prefix.replace(/\D/g, '');

        if ('' === prefixDigits) {
            return allDigits.slice(0, 10);
        }

        if (allDigits.startsWith(prefixDigits)) {
            return allDigits.slice(prefixDigits.length, prefixDigits.length + 10);
        }

        return allDigits.slice(0, 10);
    }

    extractLocalDigitsFromFormatted(value) {
        const match = value.match(/^\+\d{1,3}\s*(.*)$/);

        if (!match) {
            return '';
        }

        return match[1].replace(/\D/g, '').slice(0, 10);
    }

    formatPhone(localDigits, prefix) {
        if ('' === localDigits) {
            return prefix;
        }

        let formatted = prefix + ' (' + localDigits.slice(0, 3);

        if (localDigits.length < 3) {
            return formatted;
        }

        formatted += ') ' + localDigits.slice(3, 6);

        if (localDigits.length < 6) {
            return formatted;
        }

        formatted += '-' + localDigits.slice(6, 10);

        return formatted;
    }
}
