import { Controller } from '@hotwired/stimulus';
import { useClickOutside } from "stimulus-use";

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        page: String,
        loaded: Boolean,
        darkMode: Boolean,
        stickyMenu: Boolean,
        sidebarToggle: Boolean,
        scrollTop: Boolean,
        
        // header specific
        menuToggle: Boolean,

        // user menu specific
        userMenuDropdownOpen: Boolean
    };

    // define classes keys (real class defined in the HTML template)
    static classes = ['dark', 'hide'];

    // elements to be handled by the controller, defined in the HTML template with data-templates--base-target
    static targets = [
        'outputPageName',
        'bodyContainer',
        'pageLoader',
        'userMenuDropdown'
    ];

    initialize() {
        this.loadedValue = false;
        this.darkModeValue = false;
        this.stickyMenuValue = false;
        this.sidebarToggleValue = false;
        this.scrollTopValue = false;
        
        // header specific
        this.menuToggleValue = false;

        // user menu specific
        this.userMenuDropdownOpenValue = false;
    }

    connect() {
        // this enables the automatic event 'click:outside'
        useClickOutside(this);

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

    toggleDarkMode(event) {
        event.preventDefault();
        this.darkModeValue = !this.darkModeValue;
    }
    // watcher for dark mode changes, updates localStorage and toggles the class on body
    darkModeValueChanged(newValue, oldValue) {
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

    /**
     * Sidebar control methods
     */
    toggleSidebar() {
        this.sidebarToggleValue = !this.sidebarToggleValue;
    }
    hideSidebar() {
        this.sidebarToggleValue = false;
    }
    sidebarToggleValueChanged(newValue) {
        console.log(`Sidebar toggle changed to ${newValue}`);
        this.element.setAttribute("data-sidebar", newValue ? "collapsed" : "expanded");
    }

    /**
     * Header menu control methods
     */
    changeMenuToggle() {
        this.menuToggleValue = !this.menuToggleValue;
    }
    menuToggleValueChanged(newValue) {
        this.element.setAttribute("data-header-menu", newValue ? "expanded" : "collapsed");
    }

    /**
     * User menu dropdown control methods
     */
    closeUserMenuDropdown() {
        this.userMenuDropdownOpenValue = false;
    }
    toggleUserMenuDropdown(event) {
        event.preventDefault();
        this.userMenuDropdownOpenValue = !this.userMenuDropdownOpenValue;
    }
    userMenuDropdownOpenValueChanged(newValue) {
        if (this.hasUserMenuDropdownTarget) {
            if (newValue) {
                this.userMenuDropdownTarget.classList.remove(this.hideClass);
            } else {
                this.userMenuDropdownTarget.classList.add(this.hideClass);
            }
        }
        this.element.setAttribute("data-user-dropdown-status", newValue ? "open" : "closed");
    }
}
