import { Controller } from '@hotwired/stimulus';

const IMPACT = 0.495; // seconds: 45% of the 1.1s slam keyframes in app.css

// Slams the rubber stamp, then lets the form submit. Turbo morphs the inked result in
// (same-URL redirect = page refresh), so the impression stays put without a flash.
export default class extends Controller {
    // The stamp buttons go inert after the morph and focus would fall to <body>: park it on the decision heading.
    connect() {
        this.refocus = () => { if (document.activeElement === document.body) this.element.querySelector('h3[tabindex]')?.focus({ preventScroll: true }); };
        document.addEventListener('turbo:render', this.refocus);
    }

    disconnect() {
        document.removeEventListener('turbo:render', this.refocus);
    }

    press(event) {
        const form = event.currentTarget;
        if (form.dataset.slammed) return; // our own resubmit: let Turbo take it
        if (this.element.dataset.pressing) return event.preventDefault(); // one slam at a time
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        event.preventDefault();
        form.dataset.slammed = '1';
        this.element.dataset.pressing = event.params.kind;
        this.#thud();
        this.#burst();
        setTimeout(() => form.requestSubmit(), 1150);
    }

    // Impact sound, synthesised (no asset): low thump + short paper slap, scheduled for the moment the pad lands.
    #thud() {
        try {
            const ctx = new AudioContext();
            const t = ctx.currentTime + IMPACT;
            const out = ctx.createGain();
            out.connect(ctx.destination);

            const thump = ctx.createOscillator();
            const thumpGain = ctx.createGain();
            thump.frequency.setValueAtTime(140, t);
            thump.frequency.exponentialRampToValueAtTime(45, t + 0.18);
            thumpGain.gain.setValueAtTime(0.8, t);
            thumpGain.gain.exponentialRampToValueAtTime(0.001, t + 0.2);
            thump.connect(thumpGain).connect(out);
            thump.start(t);
            thump.stop(t + 0.22);

            const len = Math.floor(ctx.sampleRate * 0.08);
            const buf = ctx.createBuffer(1, len, ctx.sampleRate);
            const data = buf.getChannelData(0);
            for (let i = 0; i < len; i++) data[i] = (Math.random() * 2 - 1) * (1 - i / len);
            const slap = ctx.createBufferSource();
            const band = ctx.createBiquadFilter();
            const slapGain = ctx.createGain();
            band.type = 'bandpass';
            band.frequency.value = 1200;
            slapGain.gain.value = 0.6;
            slap.buffer = buf;
            slap.connect(band).connect(slapGain).connect(out);
            slap.start(t);

            setTimeout(() => ctx.close(), 1100);
        } catch {
            // no Web Audio: the stamp is silent, nothing else breaks
        }
    }

    // Paper dust + ink spatter around the pad. CSS does the timing (invisible until impact);
    // the Turbo morph removes the spans because they are not in the server HTML.
    #burst() {
        const press = this.element.querySelector('.press');
        if (!press) return;
        const rand = (min, max) => min + Math.random() * (max - min);
        const spawn = (cls, vars) => {
            const el = document.createElement('span');
            el.className = cls;
            for (const [k, v] of Object.entries(vars)) el.style.setProperty(k, v);
            press.append(el);
        };

        for (let i = 0; i < 8; i++) {
            const dir = i < 4 ? -1 : 1;
            spawn('puff', {
                '--x': `${dir * rand(2, 6)}rem`,
                '--dx': `${dir * rand(2, 5)}rem`,
                '--dy': `${rand(-3, -0.5)}rem`,
                '--s': rand(1.2, 2.2).toFixed(2),
                '--size': `${rand(1.4, 2.6).toFixed(2)}rem`,
            });
        }
        for (let i = 0; i < 12; i++) {
            const angle = rand(0, Math.PI * 2);
            const dist = rand(3, 6);
            spawn('speck', {
                '--dx': `${(Math.cos(angle) * dist * 1.4).toFixed(2)}rem`,
                '--dy': `${(Math.sin(angle) * dist * 0.7).toFixed(2)}rem`,
                '--size': `${rand(2, 5).toFixed(1)}px`,
            });
        }
    }
}
