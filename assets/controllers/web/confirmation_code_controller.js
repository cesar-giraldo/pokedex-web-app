import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'eager' */
export default class extends Controller {
    static targets = ['digit'];

    onInput(event) {
        const input = event.target;

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        const digits = input.value.replace(/\D/g, '');

        if (digits.length > 1) {
            this.fillFrom(this.indexOf(input), digits);

            return;
        }

        input.value = digits.slice(0, 1);

        if (digits.length === 1) {
            this.focusAt(this.indexOf(input) + 1);
        }
    }

    onKeydown(event) {
        if (event.key !== 'Backspace') {
            return;
        }

        const input = event.target;

        if (!(input instanceof HTMLInputElement) || input.value !== '') {
            return;
        }

        event.preventDefault();
        const previous = this.indexOf(input) - 1;

        if (previous < 0) {
            return;
        }

        this.digitTargets[previous].value = '';
        this.focusAt(previous);
    }

    onPaste(event) {
        const input = event.target;

        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        event.preventDefault();
        const text = event.clipboardData?.getData('text') ?? '';
        this.fillFrom(this.indexOf(input), text.replace(/\D/g, ''));
    }

    fillFrom(start, digits) {
        const chars = digits.slice(0, this.digitTargets.length - start).split('');

        chars.forEach((char, offset) => {
            this.digitTargets[start + offset].value = char;
        });

        const nextIndex = Math.min(start + chars.length, this.digitTargets.length - 1);
        this.focusAt(nextIndex);
    }

    indexOf(input) {
        return this.digitTargets.indexOf(input);
    }

    focusAt(index) {
        this.digitTargets[index]?.focus();
    }
}
