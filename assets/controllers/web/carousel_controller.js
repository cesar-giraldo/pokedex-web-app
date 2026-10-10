import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['slide', 'dot', 'toggle'];

    static values = {
        interval: { type: Number, default: 6500 },
        pauseLabel: String,
        playLabel: String,
    };

    connect() {
        this.index = 0;
        this.paused = false;
        this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.onKeydown = (event) => this.handleKeydown(event);
        this.element.addEventListener('keydown', this.onKeydown);
        this.show(0);

        if (!this.reduceMotion && this.slideTargets.length > 1) {
            this.start();
        } else {
            this.paused = true;
            this.renderToggle();
        }
    }

    disconnect() {
        this.stop();
        this.element.removeEventListener('keydown', this.onKeydown);
    }

    next(event) {
        event?.preventDefault();
        this.show(this.index + 1);
        this.restart();
    }

    previous(event) {
        event?.preventDefault();
        this.show(this.index - 1);
        this.restart();
    }

    go(event) {
        event.preventDefault();
        this.show(Number(event.params.index));
        this.restart();
    }

    pause() {
        this.paused = true;
        this.stop();
        this.renderToggle();
    }

    resume() {
        if (this.reduceMotion || this.slideTargets.length < 2) {
            return;
        }

        this.paused = false;
        this.start();
        this.renderToggle();
    }

    toggle(event) {
        event.preventDefault();

        if (this.paused) {
            this.resume();

            return;
        }

        this.pause();
    }

    show(index) {
        const total = this.slideTargets.length;

        if (0 === total) {
            return;
        }

        this.index = (index + total) % total;

        this.slideTargets.forEach((slide, slideIndex) => {
            const active = slideIndex === this.index;
            slide.hidden = !active;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', active ? 'false' : 'true');
        });

        this.dotTargets.forEach((dot, dotIndex) => {
            const active = dotIndex === this.index;
            dot.setAttribute('aria-current', active ? 'true' : 'false');
            dot.classList.toggle('bg-brand', active);
            dot.classList.toggle('bg-black/20', !active);
        });
    }

    start() {
        this.stop();

        if (this.paused || this.reduceMotion || this.slideTargets.length < 2) {
            return;
        }

        this.timer = window.setInterval(() => this.show(this.index + 1), this.intervalValue);
    }

    restart() {
        if (!this.paused) {
            this.start();
        }
    }

    stop() {
        if (this.timer) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    }

    handleKeydown(event) {
        if ('ArrowRight' === event.key) {
            this.next(event);
        }

        if ('ArrowLeft' === event.key) {
            this.previous(event);
        }
    }

    renderToggle() {
        if (!this.hasToggleTarget) {
            return;
        }

        this.toggleTarget.textContent = this.paused ? this.playLabelValue : this.pauseLabelValue;
        this.toggleTarget.setAttribute('aria-pressed', this.paused ? 'true' : 'false');
    }
}
