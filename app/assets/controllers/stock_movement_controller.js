import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['type', 'origin', 'groupField', 'group', 'supplierField', 'supplier', 'lines', 'line', 'lineTemplate'];

    connect() {
        this.ocrWorkerPromise = null;
        this.stampBrowserTime();
        this.refresh();
    }

    disconnect() {
        this.ocrWorkerPromise?.then((worker) => worker.terminate());
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
        const entry = this.isEntry;
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
        const lot = line.querySelector('[data-line-lot]');
        lot.hidden = !entry;
        lot.querySelectorAll('input,button').forEach((field) => field.disabled = !entry);
    }

    scanLot(event) {
        const line = event.currentTarget.closest('[data-stock-movement-target~="line"]');
        line.querySelector('[data-line-lot-camera]').click();
    }

    async readLotImage(event) {
        const file = event.currentTarget.files?.[0];
        const line = event.currentTarget.closest('[data-stock-movement-target~="line"]');
        const status = line.querySelector('[data-line-lot-status]');
        const field = line.querySelector('[data-line-lot-value]');
        if (!file) return;

        status.textContent = 'Analyse du code et du texte en cours…';
        try {
            let barcodeLot = null;
            if ('createImageBitmap' in window) {
                try {
                    const image = await createImageBitmap(file);
                    barcodeLot = await this.readBarcodeLot(image);
                    image.close();
                } catch (error) {
                    console.info('Lecture du code-barres indisponible, passage à l’OCR', error);
                }
            }
            if (barcodeLot) {
                field.value = barcodeLot;
                status.textContent = 'Lot lu dans le code GS1. Vérifiez la valeur avant d’enregistrer.';
                return;
            }

            status.textContent = 'Aucun lot GS1 trouvé. Reconnaissance du texte en cours…';
            const worker = await this.ocrWorker();
            const result = await worker.recognize(file);
            const ocrLot = this.extractLot(result.data.text);
            if (ocrLot) {
                field.value = ocrLot;
                status.textContent = 'Lot détecté par OCR. Vérifiez la valeur avant d’enregistrer.';
            } else {
                status.textContent = 'Numéro de lot non identifié. Vous pouvez le saisir manuellement.';
                field.focus();
            }
        } catch (error) {
            console.error('Lecture du numéro de lot impossible', error);
            status.textContent = 'Lecture automatique impossible. Saisissez le numéro manuellement.';
            field.focus();
        } finally {
            event.currentTarget.value = '';
        }
    }

    async ocrWorker() {
        if (!this.ocrWorkerPromise) {
            const ocrBaseUrl = new URL('/ocr/', window.location.origin).href;
            this.ocrWorkerPromise = import('tesseract.js')
                .then(({ createWorker }) => createWorker('fra', 1, {
                    workerPath: `${ocrBaseUrl}worker.min.js`,
                    corePath: `${ocrBaseUrl}tesseract-core-simd-lstm.wasm.js`,
                    langPath: ocrBaseUrl,
                    workerBlobURL: false,
                }))
                .catch((error) => {
                    this.ocrWorkerPromise = null;
                    throw error;
                });
        }
        return this.ocrWorkerPromise;
    }

    async readBarcodeLot(image) {
        if (!('BarcodeDetector' in window)) return null;
        const supported = await BarcodeDetector.getSupportedFormats();
        const formats = ['data_matrix', 'qr_code', 'code_128', 'ean_13'].filter((format) => supported.includes(format));
        if (!formats.length) return null;
        const codes = await new BarcodeDetector({ formats }).detect(image);
        for (const code of codes) {
            const lot = this.extractGs1Lot(code.rawValue || '');
            if (lot) return lot;
        }
        return null;
    }

    extractGs1Lot(raw) {
        const value = raw.replace(/^]C1|^]d2/i, '');
        const digitalLink = value.match(/\/10\/([^/?#]+)/i);
        if (digitalLink) return decodeURIComponent(digitalLink[1]).slice(0, 100);
        const parenthesized = value.match(/\(10\)([A-Z0-9!"%&'()*+,\-./:;<=>?_ ]{1,20}?)(?=\(\d{2,4}\)|$)/i);
        if (parenthesized) return parenthesized[1].trim();
        const element = value.match(/^(?:01.{14})?(?:1[157].{6})*10([^\x1d]{1,20})(?:\x1d|$)/i)
            || value.match(/(?:^|\x1d)10([^\x1d]{1,20})(?:\x1d|$)/i);
        return element ? element[1].trim() : null;
    }

    extractLot(text) {
        const normalized = text.replace(/[|]/g, 'I');
        const patterns = [
            /(?:N[°º]?\s*(?:DE\s*)?LOT|LOT|BATCH)\s*[:#.-]?\s*([A-Z0-9][A-Z0-9._/-]{2,30})/i,
            /\bL(?:OT)?\s*[:#.-]\s*([A-Z0-9][A-Z0-9._/-]{2,30})/i,
        ];
        for (const pattern of patterns) {
            const match = normalized.match(pattern);
            if (match) return match[1].replace(/[.,;:]$/, '').slice(0, 100);
        }
        return null;
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
