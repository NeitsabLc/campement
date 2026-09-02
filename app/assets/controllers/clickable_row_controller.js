import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    open(event) {
        if (event.target.closest('a, button, form, input, select, textarea')) return;
        if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;

        event.preventDefault();
        if (this.element.dataset.url) {
            window.location.assign(this.element.dataset.url);
            return;
        }

        this.element.querySelector(this.element.dataset.clickTarget)?.click();
    }
}
