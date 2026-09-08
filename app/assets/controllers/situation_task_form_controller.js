import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['type', 'freeLabel'];

    connect() {
        this.refresh();
    }

    refresh() {
        const isFreeTask = this.typeTarget.value === '';
        this.freeLabelTarget.hidden = !isFreeTask;
        this.freeLabelTarget.querySelector('input').required = isFreeTask;
    }
}
