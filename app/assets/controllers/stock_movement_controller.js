import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['type', 'origin', 'groupField', 'group', 'supplierField', 'supplier', 'lines', 'line', 'lineTemplate'];

    connect() {
        this.stampBrowserTime();
        this.refresh();
    }

    stampBrowserTime() {
        const field = this.element.querySelector('[data-browser-datetime]');
        if (field) field.value = new Date().toISOString();
    }

    refresh() {
        const distribution = this.originCode === 'DISTRIBUTION';
        this.groupFieldTarget.hidden = !distribution;
        this.groupTarget.disabled = !distribution;
        this.groupTarget.required = distribution;
        const supplierEntry = this.isEntry && this.originCode === 'FOURNISSEUR';
        this.supplierFieldTarget.hidden = !supplierEntry;
        this.supplierTarget.disabled = !supplierEntry;
        this.supplierTarget.required = supplierEntry;
        this.lineTargets.forEach((line) => this.updateLine(line));
        this.numberLines();
    }

    refreshLine(event) {
        this.updateLine(event.target.closest('[data-stock-movement-target~="line"]'));
    }

    addLine() {
        const index = this.nextIndex();
        this.linesTarget.insertAdjacentHTML('beforeend', this.lineTemplateTarget.innerHTML.replaceAll('__INDEX__', String(index)));
        this.updateLine(this.lineTargets.at(-1));
        this.numberLines();
    }

    removeLine(event) {
        if (this.lineTargets.length === 1) return;
        event.currentTarget.closest('[data-stock-movement-target~="line"]').remove();
        this.numberLines();
    }

    updateLine(line) {
        if (!line) return;
        const supplierEntry = this.isEntry && this.originCode === 'FOURNISSEUR';
        let food = line.querySelector('[data-line-food]')?.value || '';
        const foodSelect = line.querySelector('[data-line-food]');
        [...foodSelect.options].forEach((option) => {
            const suppliers = (option.dataset.suppliers || '').trim().split(/\s+/).filter(Boolean);
            const visible = !option.value || !supplierEntry || (!!this.supplierTarget.value && suppliers.includes(this.supplierTarget.value));
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (foodSelect.selectedOptions[0]?.disabled) foodSelect.value = '';
        food = foodSelect.value;
        const exit = line.querySelector('[data-line-exit]');
        const entryBlock = line.querySelector('[data-line-entry]');
        exit.hidden = supplierEntry;
        entryBlock.hidden = !supplierEntry;
        exit.querySelectorAll('select,input').forEach((field) => field.disabled = supplierEntry);
        entryBlock.querySelectorAll('select,input').forEach((field) => field.disabled = !supplierEntry);

        const exitUnit = line.querySelector('[data-line-exit-unit]');
        this.filterOptions(exitUnit, food);
        exitUnit.required = !supplierEntry;
        line.querySelector('[data-line-quantity]').required = !supplierEntry;
        line.querySelector('[data-line-unit]').textContent = exitUnit.selectedOptions[0]?.dataset.symbol || '—';

        const reference = line.querySelector('[data-line-reference]');
        [...reference.options].forEach((option) => {
            const visible = !option.value || (option.dataset.food === foodSelect.value && option.dataset.supplier === this.supplierTarget.value);
            option.hidden = !visible;
            option.disabled = !visible;
        });
        const referenceDisponible = [...reference.options].find((option) => option.value && !option.disabled);
        reference.value = supplierEntry && referenceDisponible ? referenceDisponible.value : '';
        reference.required = supplierEntry;
        line.querySelectorAll('[data-line-packagings]').forEach((block) => {
            const active = supplierEntry && block.dataset.reference === reference.value;
            block.hidden = !active;
            block.querySelectorAll('input').forEach((input) => input.disabled = !active);
        });
        const hasSupplier = [...reference.options].some((option) => option.value && !option.disabled);
        line.querySelector('[data-line-no-supplier]').hidden = !supplierEntry || !foodSelect.value || hasSupplier;
    }

    filterOptions(select, food) {
        [...select.options].forEach((option) => {
            const visible = !option.value || option.dataset.food === food;
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (select.selectedOptions[0]?.disabled) select.value = '';
    }

    numberLines() {
        this.lineTargets.forEach((line, index) => {
            const number = line.querySelector('[data-line-number]');
            if (number) number.textContent = this.lineTargets.length > 1 ? String(index + 1) : '';
            const remove = line.querySelector('[data-action~="stock-movement#removeLine"]');
            if (remove) remove.hidden = this.lineTargets.length === 1;
        });
    }

    nextIndex() {
        return this.lineTargets.reduce((max, line) => {
            const name = line.querySelector('[name^="lignes["]')?.name || '';
            const match = name.match(/^lignes\[(\d+)]/);
            return Math.max(max, match ? Number(match[1]) + 1 : 0);
        }, 0);
    }

    get isEntry() {
        return this.typeTargets.find((input) => input.checked)?.value === 'ENTREE';
    }

    get originCode() {
        return this.originTarget.selectedOptions[0]?.dataset.code || '';
    }
}
