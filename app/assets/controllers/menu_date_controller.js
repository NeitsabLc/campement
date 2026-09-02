import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input'];

    open() {
        if (typeof this.inputTarget.showPicker === 'function') {
            this.inputTarget.showPicker();
            return;
        }

        this.inputTarget.focus();
        this.inputTarget.click();
    }

    navigate() {
        if (this.inputTarget.value && this.inputTarget.checkValidity()) {
            this.element.requestSubmit();
        }
    }
}
