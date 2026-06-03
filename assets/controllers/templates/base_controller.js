import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        page: String,
        loaded: Boolean,
        darkMode: Boolean,
        stickyMenu: Boolean,
        sidebarToggle: Boolean,
        scrollTop: Boolean
    };

    // define classes keys (real class defined in the HTML template)
    static classes = ['dark', 'hide'];

    // elements to be handled by the controller, defined in the HTML template with data-templates--base-target
    static targets = ['outputPageName', 'bodyContainer', 'pageLoader'];

    initialize() {
        this.loadedValue = false;
        this.darkModeValue = false;
        this.stickyMenuValue = false;
        this.sidebarToggleValue = false;
        this.scrollTopValue = false;
    }

    connect() {
        console.log('Page Name: ' + this.pageValue);
        this.updatePageValue();
        this.darkModeValue = JSON.parse(localStorage.getItem('darkMode')) || false;

        // loader simulation
        setTimeout(() => {
            this.loadedValue = true
        }, 500)
    }

    disconnect() {
    }

    updatePageValue() {
        if (this.hasOutputPageNameTarget) {
            this.outputPageNameTarget.textContent = this.pageValue;
        }
    }

    // watcher for dark mode changes, updates localStorage and toggles the class on body
    darkModeValueChanged(newValue, oldValue) {
        console.log(`Dark mode changed from ${oldValue} to ${newValue}`);
        localStorage.setItem('darkMode', JSON.stringify(newValue));
        if (this.hasBodyContainerTarget) {
            this.bodyContainerTarget.classList.toggle(this.darkClass, newValue)
        }

    }

    // watcher for page loaded state, shows or hides the loader based on the value
    loadedValueChanged(newValue) {
        if (this.hasPageLoaderTarget) {
            if (newValue) {
                this.pageLoaderTarget.classList.add(this.hideClass);
            } else {
                this.pageLoaderTarget.classList.remove(this.hideClass);
            }
        }
    }
}
