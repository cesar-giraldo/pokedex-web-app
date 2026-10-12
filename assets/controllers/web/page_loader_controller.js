import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'eager' */

const SHOW_DELAY = 200;
const MAX_WAIT = 1000;

let bound = false;
let showTimer = 0;
let capTimer = 0;
let token = 0;

export default class extends Controller {
    connect() {
        bindOnce();
        settle();
    }
}

function bindOnce() {
    if (bound) {
        return;
    }

    bound = true;
    document.addEventListener('turbo:visit', arm);
    document.addEventListener('turbo:before-cache', concealForCache);
    document.addEventListener('turbo:render', settle);
    document.addEventListener('turbo:load', settle);
    document.addEventListener('turbo:fetch-request-error', reveal);
}

function overlay() {
    return document.getElementById('page-loader');
}

function arm() {
    window.clearTimeout(showTimer);
    showTimer = window.setTimeout(cover, SHOW_DELAY);
}

function cover() {
    const loader = overlay();

    if (!loader) {
        return;
    }

    document.documentElement.dataset.pageLoader = 'wait';
    loader.hidden = false;
    document.body.setAttribute('aria-busy', 'true');
    setInert(true);
}

function concealForCache() {
    const loader = overlay();

    if (!loader) {
        return;
    }

    loader.hidden = true;
    document.body.removeAttribute('aria-busy');
    setInert(false);
}

function settle() {
    const current = ++token;
    const started = performance.now();

    if ('wait' === document.documentElement.dataset.pageLoader) {
        cover();
    }

    waitForLayout(current).then((pending) => {
        if (current !== token || null === pending) {
            return;
        }

        const remaining = MAX_WAIT - (performance.now() - started);

        if (0 === pending.length || remaining <= 0) {
            reveal();

            return;
        }

        if ('wait' === document.documentElement.dataset.pageLoader) {
            cover();
        } else {
            arm();
        }

        window.clearTimeout(capTimer);
        const cap = new Promise((resolve) => {
            capTimer = window.setTimeout(resolve, remaining);
        });

        Promise.race([
            Promise.all(pending.map((image) => whenDone(image))),
            cap,
        ]).then(() => {
            if (current === token) {
                reveal();
            }
        });
    });
}

function waitForLayout(current) {
    const started = performance.now();

    return new Promise((resolve) => {
        const measure = () => {
            if (current !== token) {
                resolve(null);

                return;
            }

            const result = pendingImages();

            if (!result.unready || performance.now() - started >= MAX_WAIT) {
                resolve(result.pending);

                return;
            }

            window.requestAnimationFrame(measure);
        };

        window.requestAnimationFrame(measure);
    });
}

function reveal() {
    token += 1;
    window.clearTimeout(showTimer);
    window.clearTimeout(capTimer);
    delete document.documentElement.dataset.pageLoader;

    const loader = overlay();

    if (loader) {
        loader.hidden = true;
    }

    document.body.removeAttribute('aria-busy');
    setInert(false);
}

function pendingImages() {
    const loader = overlay();
    const viewport = window.innerHeight;
    const pending = [];
    let unready = false;

    document.querySelectorAll('main img').forEach((image) => {
        if (loader?.contains(image) || image.complete || image.closest('[hidden]')) {
            return;
        }

        const rect = image.getBoundingClientRect();

        if (0 === rect.width && 0 === rect.height) {
            unready = true;

            return;
        }

        if (rect.top < viewport && rect.bottom > 0) {
            pending.push(image);
        }
    });

    return { pending, unready: unready && 0 === pending.length };
}

function whenDone(image) {
    if (image.complete) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        const done = () => {
            image.removeEventListener('load', done);
            image.removeEventListener('error', done);
            resolve();
        };

        image.addEventListener('load', done);
        image.addEventListener('error', done);
    });
}

function setInert(inert) {
    const loader = overlay();

    [...document.body.children].forEach((child) => {
        if (child === loader) {
            return;
        }

        if (inert) {
            child.setAttribute('inert', '');
        } else {
            child.removeAttribute('inert');
        }
    });
}
