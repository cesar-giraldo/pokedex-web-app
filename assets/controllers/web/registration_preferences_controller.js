import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'eager' */
export default class extends Controller {
    static targets = ['timezone', 'locale', 'timeFormat'];

    connect() {
        const resolved = Intl.DateTimeFormat().resolvedOptions();

        if (this.hasTimezoneTarget && this.timezoneTarget.value === '') {
            this.timezoneTarget.value = resolved.timeZone || '';
        }

        if (this.hasLocaleTarget && this.localeTarget.value === '') {
            this.localeTarget.value = navigator.language || '';
        }

        if (this.hasTimeFormatTarget && this.timeFormatTarget.value === '') {
            this.timeFormatTarget.value = this.hourFormat(resolved);
        }
    }

    hourFormat(resolved) {
        const hourCycle = resolved.hourCycle;

        if (hourCycle === 'h23' || hourCycle === 'h24' || resolved.hour12 === false) {
            return '24h';
        }

        if (hourCycle === 'h11' || hourCycle === 'h12' || resolved.hour12 === true) {
            return '12h';
        }

        return '';
    }
}
