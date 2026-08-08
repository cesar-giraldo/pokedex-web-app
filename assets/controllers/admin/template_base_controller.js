import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        page: String,
        loaded: Boolean,
        darkMode: Boolean,
        stickyMenu: Boolean,
        scrollTop: Boolean,

        // Sidebar specific
        sidebarToggle: Boolean,
        selectedMenu: String,
        

        // header specific
        menuToggle: Boolean,
    };

    // define classes keys (real class defined in the HTML template)
    static classes = ['dark', 'hide'];

    // elements to be handled by the controller, defined in the HTML template with data-templates--base-target
    static targets = [
        'outputPageName',
        'bodyContainer',
        'pageLoader'
    ];

    initialize() {
        this.loadedValue = false;
        this.stickyMenuValue = false;
        this.sidebarToggleValue = false;
        this.scrollTopValue = false;

        // header specific
        this.menuToggleValue = false;

        const storedDarkMode = localStorage.getItem('darkMode');
        if (storedDarkMode === null) {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            this.darkModeValue = prefersDark ? true : false;
        } else {
            this.darkModeValue = JSON.parse(storedDarkMode);
        }
    }

    connect() {
        console.log('Page Name: ' + this.pageValue);
        this.updatePageValue();

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
     * Header menu control methods
     */
    changeMenuToggle() {
        this.menuToggleValue = !this.menuToggleValue;
    }
    menuToggleValueChanged(newValue) {
        this.element.setAttribute("data-header-menu", newValue ? "expanded" : "collapsed");
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
        this.element.setAttribute("data-sidebar", newValue ? "collapsed" : "expanded");
    }

    setSelectedMenu(event) {
        event.preventDefault();
        this.selectedMenuValue = event.params.selectedMenu;
    }

    selectedMenuValueChanged(newMenu, oldMenu) {
        // Desactivamos el menú anterior si existía
        if (oldMenu) {
            this.toggleMenuState(oldMenu, false);
        }

        // Activamos el nuevo menú elegido
        if (newMenu) {
            this.toggleMenuState(newMenu, true);
        }
    }

    toggleMenuState(menuName, isActive) {
        const group = this.element.querySelector(`.group-menu-${menuName}`);
        if (!group) return;

        const link = group.querySelector('a.menu-item');
        if (link) {
            // Alterna las clases principales del enlace
            link.classList.toggle('menu-item-active', isActive);
            link.classList.toggle('menu-item-inactive', !isActive);

            // Alterna las clases del primer SVG (icono principal)
            const icon = link.querySelector('svg');
            if (icon) {
                icon.classList.toggle('menu-item-icon-active', isActive);
                icon.classList.toggle('menu-item-icon-inactive', !isActive);
            }

            // Alterna las clases del SVG de la flecha
            const arrow = link.querySelector('svg.menu-item-arrow');
            if (arrow) {
                arrow.classList.toggle('menu-item-arrow-active', isActive);
                arrow.classList.toggle('menu-item-arrow-inactive', !isActive);
            }
        }

        // Muestra u oculta el contenedor desplegable (dropdown)
        const dropdown = group.querySelector('div.overflow-hidden');
        if (dropdown) {
            dropdown.classList.toggle('block', isActive);
            dropdown.classList.toggle('hidden', !isActive);
        }
    }
}
