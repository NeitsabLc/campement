import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['catalog', 'flash', 'picker', 'rows', 'template'];

    connect() {
        this.syncOptions();
        this.flashTargets.forEach((flash) => {
            window.setTimeout(() => {
                flash.classList.add('flash--leaving');
                flash.addEventListener('transitionend', () => flash.remove(), { once: true });
                window.setTimeout(() => flash.remove(), 300);
            }, 3000);
        });
    }

    add(event) {
        if (event?.type === 'keydown') event.preventDefault();

        const recherche = this.pickerTarget.value.trim().toLocaleLowerCase('fr');
        const option = [...this.catalogTarget.options].find(
            (candidate) => !candidate.disabled && candidate.value.toLocaleLowerCase('fr') === recherche,
        );
        if (!option || this.rowsTarget.querySelector(`[data-denree-id="${option.dataset.id}"]`)) return;

        const html = this.templateTarget.innerHTML
            .replaceAll('__ID__', option.dataset.id)
            .replaceAll('__NAME__', option.value)
            .replaceAll('__UNIT__', option.dataset.unit);
        this.rowsTarget.insertAdjacentHTML('beforeend', html);
        this.pickerTarget.value = '';
        this.syncOptions();
    }

    remove(event) {
        event.currentTarget.closest('.food-row').remove();
        this.syncOptions();
    }

    syncOptions() {
        const selected = new Set([...this.rowsTarget.querySelectorAll('[data-denree-id]')].map((row) => row.dataset.denreeId));
        [...this.catalogTarget.options].forEach((option) => {
            const isSelected = selected.has(option.dataset.id);
            option.disabled = isSelected;
            option.hidden = isSelected;
        });
    }
}
