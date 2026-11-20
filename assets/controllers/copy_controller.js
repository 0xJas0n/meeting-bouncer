import { Controller } from '@hotwired/stimulus';

// Copies the source field's text. The field stays selectable, so manual copy still works without clipboard access.
// Optional: a template with {sender} is filled with a name the user types once (kept in this browser only, never sent to the server).
export default class extends Controller {
    static targets = ['source', 'label', 'name', 'mail'];
    static values = { done: { type: String, default: 'Copied' }, template: String, subject: String, to: String };

    connect() {
        if (this.hasNameTarget) {
            try { this.nameTarget.value = localStorage.getItem('sender-name') ?? ''; } catch { /* storage blocked */ }
        }
        this.render();
    }

    rename() {
        try { localStorage.setItem('sender-name', this.nameTarget.value.trim()); } catch { /* storage blocked */ }
        this.render();
    }

    render() {
        if (!this.hasTemplateValue) return;
        const name = this.hasNameTarget ? this.nameTarget.value.trim() : '';
        const text = this.templateValue.replaceAll('{sender}', name).trimEnd();
        this.sourceTarget.value = text;
        if (this.hasMailTarget) {
            this.mailTarget.href = `mailto:${encodeURIComponent(this.toValue)}?subject=${encodeURIComponent(this.subjectValue)}&body=${encodeURIComponent(text)}`;
        }
    }

    async copy() {
        try {
            await navigator.clipboard.writeText(this.sourceTarget.value);
        } catch {
            this.sourceTarget.select();
            return;
        }
        const label = this.labelTarget.textContent;
        this.labelTarget.textContent = this.doneValue;
        setTimeout(() => (this.labelTarget.textContent = label), 1500);
    }
}
