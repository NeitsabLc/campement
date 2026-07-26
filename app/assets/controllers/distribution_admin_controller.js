import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['link', 'copyButton'];

    async copy() {
        await navigator.clipboard.writeText(this.linkTarget.value);
        const original = this.copyButtonTarget.textContent;
        this.copyButtonTarget.textContent = 'Copié !';
        window.setTimeout(() => this.copyButtonTarget.textContent = original, 1800);
    }
}
