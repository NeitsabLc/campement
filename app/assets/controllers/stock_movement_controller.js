import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['type', 'food', 'origin', 'exitFields', 'entryFields', 'exitInput', 'exitUnit', 'groupField', 'group', 'unit', 'reference', 'packagings', 'noSupplier'];

    connect() {
        this.stampBrowserTime();
        this.refresh();
    }

    stampBrowserTime() {
        this.element.querySelector('[data-browser-datetime]').value = new Date().toISOString();
    }

    refresh() {
        const entry = this.typeTargets.find((input) => input.checked)?.value === 'ENTREE';
        this.entryFieldsTarget.hidden = !entry;
        this.exitFieldsTarget.hidden = entry;
        this.entryFieldsTarget.querySelectorAll('select,input').forEach((field) => field.disabled = !entry);
        this.exitFieldsTarget.querySelectorAll('select,input').forEach((field) => field.disabled = entry);

        const distribution = this.originTarget.selectedOptions[0]?.dataset.code === 'DISTRIBUTION';
        this.groupFieldTarget.hidden = entry || !distribution;
        this.groupTarget.disabled = entry || !distribution;
        this.groupTarget.required = !entry && distribution;
        this.exitInputTarget.required = !entry;
        [...this.exitUnitTarget.options].forEach((option) => {
            const visible = !option.value || option.dataset.food === this.foodTarget.value;
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (this.exitUnitTarget.selectedOptions[0]?.disabled) this.exitUnitTarget.value = '';
        this.exitUnitTarget.required = !entry;
        this.unitTarget.textContent = this.exitUnitTarget.selectedOptions[0]?.dataset.symbol || '—';

        [...this.referenceTarget.options].forEach((option) => {
            const visible = !option.value || option.dataset.food === this.foodTarget.value;
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (this.referenceTarget.selectedOptions[0]?.disabled) this.referenceTarget.value = '';
        this.referenceTarget.required = entry;
        this.refreshPackagings();
    }

    refreshPackagings() {
        const selected = this.referenceTarget.value;
        this.packagingsTargets.forEach((block) => {
            const active = block.dataset.reference === selected;
            block.hidden = !active;
            block.querySelectorAll('input').forEach((input) => input.disabled = !active);
        });
        const hasSupplier = [...this.referenceTarget.options].some((option) => option.value && !option.disabled);
        this.noSupplierTarget.hidden = !this.entryFieldsTarget.hidden && hasSupplier;
    }
}
