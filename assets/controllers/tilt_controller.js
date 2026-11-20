import { Controller } from '@hotwired/stimulus';

// Pointer-driven 3D tilt + glare. Only writes CSS vars; app.css does the rest.
export default class extends Controller {
    static values = { max: { type: Number, default: 3 } };

    move(event) {
        if (event.pointerType !== 'mouse' || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const r = this.element.getBoundingClientRect();
        const x = (event.clientX - r.left) / r.width;
        const y = (event.clientY - r.top) / r.height;
        const s = this.element.style;
        s.setProperty('--ry', `${(x - 0.5) * this.maxValue}deg`);
        s.setProperty('--rx', `${(0.5 - y) * this.maxValue}deg`);
        s.setProperty('--gx', `${x * 100}%`);
        s.setProperty('--gy', `${y * 100}%`);
    }

    reset() {
        ['--rx', '--ry', '--gx', '--gy'].forEach((p) => this.element.style.removeProperty(p));
    }
}
