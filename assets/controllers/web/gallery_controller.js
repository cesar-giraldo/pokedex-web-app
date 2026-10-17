import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['dialog', 'figure', 'image', 'caption', 'loader', 'item', 'previousButton', 'nextButton', 'closeButton'];

    connect() {
        this.currentIndex = 0;
        this.pendingToken = 0;
        this.onResize = () => {
            if (this.dialogTarget.open) {
                this.fitImage();
            }
        };
        window.addEventListener('resize', this.onResize);
    }

    disconnect() {
        window.removeEventListener('resize', this.onResize);
    }

    open(event) {
        event.preventDefault();

        const index = this.itemTargets.indexOf(event.currentTarget);
        if (index < 0) {
            return;
        }

        this.present(index);
    }

    openFirst(event) {
        event.preventDefault();

        if (this.itemTargets.length < 1) {
            return;
        }

        this.present(0);
    }

    present(index) {
        this.currentIndex = index;
        this.showCurrent(() => {
            if (!this.dialogTarget.open) {
                this.dialogTarget.showModal();
            }

            if (this.hasCloseButtonTarget) {
                this.closeButtonTarget.focus();
            }
        });
    }

    close(event) {
        event?.preventDefault();

        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    clear() {
        this.imageTarget.removeAttribute('src');
        this.setLoading(false);
        this.imageTarget.style.width = '';
        this.imageTarget.style.height = '';
        if (this.hasFigureTarget) {
            this.figureTarget.style.width = '';
        }
        this.pendingToken += 1;
    }

    backdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    keydown(event) {
        if (!this.dialogTarget.open || event.altKey || event.metaKey || event.ctrlKey) {
            return;
        }

        if (event.key === 'ArrowLeft') {
            this.previous(event);
        } else if (event.key === 'ArrowRight') {
            this.next(event);
        }
    }

    previous(event) {
        this.move(-1, event);
    }

    next(event) {
        this.move(1, event);
    }

    showCurrent(onReady) {
        const item = this.itemTargets[this.currentIndex];
        if (!(item instanceof HTMLElement)) {
            return;
        }

        const src = item.dataset.gallerySrcParam ?? '';
        const alt = item.dataset.galleryAltParam ?? '';
        const token = ++this.pendingToken;

        this.imageTarget.alt = alt;
        this.captionTarget.textContent = alt;
        this.captionTarget.hidden = alt === '';
        this.toggleNav(this.hasPreviousButtonTarget ? this.previousButtonTarget : null, this.itemTargets.length > 1);
        this.toggleNav(this.hasNextButtonTarget ? this.nextButtonTarget : null, this.itemTargets.length > 1);

        const reveal = () => {
            if (token !== this.pendingToken) {
                return;
            }

            this.setLoading(false);
            this.fitImage();

            if (typeof onReady === 'function') {
                onReady();
            }
        };

        if (src === '') {
            this.imageTarget.removeAttribute('src');
            reveal();

            return;
        }

        if (this.imageTarget.getAttribute('src') === src && this.imageTarget.complete) {
            reveal();

            return;
        }

        this.setLoading(true);
        this.imageTarget.addEventListener('load', reveal, { once: true });
        this.imageTarget.addEventListener('error', reveal, { once: true });
        this.imageTarget.src = src;
    }

    setLoading(isLoading) {
        if (!this.hasLoaderTarget) {
            return;
        }

        this.loaderTarget.hidden = !isLoading;
    }

    move(direction, event) {
        if (!this.dialogTarget.open || this.itemTargets.length < 2) {
            return;
        }

        event?.preventDefault();
        const total = this.itemTargets.length;
        this.currentIndex = (this.currentIndex + direction + total) % total;
        this.showCurrent();
    }

    fitImage() {
        const image = this.imageTarget;
        if (image.naturalWidth < 1 || image.naturalHeight < 1) {
            return;
        }

        const maxWidth = Math.min(60 * 16, window.innerWidth - 32);
        const maxHeight = Math.min(window.innerHeight * 0.7, window.innerHeight - 128);
        const scale = Math.min(maxWidth / image.naturalWidth, maxHeight / image.naturalHeight, 1);
        const width = Math.max(1, Math.round(image.naturalWidth * scale));
        const height = Math.max(1, Math.round(image.naturalHeight * scale));

        image.style.width = `${width}px`;
        image.style.height = `${height}px`;

        if (this.hasFigureTarget) {
            this.figureTarget.style.width = `${width}px`;
        }
    }

    toggleNav(button, show) {
        if (!(button instanceof HTMLElement)) {
            return;
        }

        button.toggleAttribute('hidden', !show);
    }
}
