import { Controller } from '@hotwired/stimulus';

// Odometer roll: every digit of the server-rendered text becomes a column that scrolls
// from 0 to its value (low digits spin further). The text stays in an .sr-only span.
// Turbo morph resets our markup to plain text; the observer re-renders it, and only
// animates when the value differs from the one last seen (kept in localStorage per key),
// so reloads and revisits show the plain number.
const ROW = 1.15; // em, keep in sync with .odo-col in app.css

export default class extends Controller {
    static values = { key: String };

    connect() {
        this.observer = new MutationObserver(() => this.render());
        this.render();
        this.watch();
    }

    // Always watch: morph edits the text node in place (characterData), also when nothing rolled yet.
    watch() {
        this.observer.observe(this.element, { childList: true, characterData: true, subtree: true });
    }

    disconnect() {
        this.observer.disconnect();
    }

    render() {
        if (this.element.querySelector('.odo')) return;
        const text = this.element.textContent.trim();
        const storeKey = `odometer:${this.keyValue}`;
        let seen = null;
        try {
            seen = localStorage.getItem(storeKey);
            localStorage.setItem(storeKey, text);
        } catch {}
        // First ever visit (seen === null) counts as no change.
        if (seen === null || seen === text || matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        this.observer.disconnect();
        const digits = text.replace(/\D/g, '').length;
        let left = 0;
        const cols = [];
        const html = [...text].map((ch) => {
            if (!/\d/.test(ch)) return `<span>${ch === ' ' ? '&nbsp;' : ch}</span>`;
            const fromRight = digits - 1 - left;
            const rows = 10 * (1 + Math.min(fromRight, 2)) + Number(ch);
            cols.push({ rows, delay: left++ * 60 });
            return `<span class="odo-col"><ul>${Array.from({ length: rows + 1 }, (_, i) => `<li>${i % 10}</li>`).join('')}</ul></span>`;
        }).join('');
        this.element.innerHTML = `<span class="sr-only">${text}</span><span class="odo" aria-hidden="true">${html}</span>`;
        this.element.querySelectorAll('.odo-col ul').forEach((ul, i) => {
            ul.style.transitionDelay = `${cols[i].delay}ms`;
            ul.style.setProperty('--y', `${-cols[i].rows * ROW}em`);
        });
        void this.element.offsetWidth; // commit the start position, else no transition runs
        this.element.querySelector('.odo').classList.add('rolling');
        this.watch();
    }
}
