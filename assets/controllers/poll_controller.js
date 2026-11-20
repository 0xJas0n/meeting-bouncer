import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

// Re-fetches the current page every few seconds; Turbo morphs the diff in (no reload, no flash).
// The element renders only while work is pending, so morphing it away stops the polling.
export default class extends Controller {
    static values = { every: { type: Number, default: 2000 } };

    connect() {
        document.documentElement.classList.add('polling'); // hides the top progress bar, see app.css
        this.timer = setInterval(() => document.hidden || Turbo.visit(location.href, { action: 'replace' }), this.everyValue);
    }

    disconnect() {
        clearInterval(this.timer);
        document.documentElement.classList.remove('polling');
    }
}
