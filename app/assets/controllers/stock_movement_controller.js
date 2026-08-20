import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['type', 'origin', 'groupField', 'group', 'supplierField', 'supplier', 'lines', 'line', 'lineTemplate', 'catalog'];

    connect() {
        this.catalog = JSON.parse(this.catalogTarget.textContent);
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
        const allowedOrigins = new Set(this.isEntry
            ? ['INVENTAIRE', 'FOURNISSEUR', 'RETOUR_ALIMENTAIRE', 'CORRECTION']
            : ['INVENTAIRE', 'DISTRIBUTION', 'POUBELLE', 'DONATION', 'CORRECTION']);
        [...this.originTarget.options].forEach((option) => {
            const visible = !option.value || allowedOrigins.has(option.dataset.code || '');
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (this.originTarget.selectedOptions[0]?.disabled) this.originTarget.value = '';

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
        this.updateLine(this.lineTargets[this.lineTargets.length - 1]);
        this.numberLines();
    }

    removeLine(event) {
        if (this.lineTargets.length === 1) return;
        event.currentTarget.closest('[data-stock-movement-target~="line"]').remove();
        this.numberLines();
    }

    updateLine(line) {
        if (!line) return;
        this.initializeLine(line);
        const supplierEntry = this.isEntry && this.originCode === 'FOURNISSEUR';
        const inventoryMovement = this.originCode === 'INVENTAIRE';
        const packagedMovement = supplierEntry || inventoryMovement;
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
        exit.hidden = packagedMovement;
        entryBlock.hidden = !packagedMovement;
        exit.querySelectorAll('select,input').forEach((field) => field.disabled = packagedMovement);
        entryBlock.querySelectorAll('select,input').forEach((field) => field.disabled = !packagedMovement);

        const exitUnit = line.querySelector('[data-line-exit-unit]');
        this.populateExitUnits(line, exitUnit, food);
        exitUnit.required = !packagedMovement;
        line.querySelector('[data-line-quantity]').required = !packagedMovement;
        line.querySelector('[data-line-unit]').textContent = exitUnit.selectedOptions[0]?.dataset.symbol || '—';

        const reference = line.querySelector('[data-line-reference]');
        const inventorySupplier = line.querySelector('[data-line-inventory-supplier]');
        inventorySupplier.hidden = !inventoryMovement;
        this.populateReferences(line, reference, foodSelect.value, this.supplierTarget.value, supplierEntry, inventoryMovement);
        reference.required = packagedMovement;
        this.populatePackagings(line, reference, packagedMovement);
        const hasSupplier = [...reference.options].some((option) => option.value && !option.disabled);
        line.querySelector('[data-line-no-supplier]').hidden = !packagedMovement || !foodSelect.value || hasSupplier;
        const lot = line.querySelector('[data-line-lot]');
        const lotAvailable = supplierEntry && Boolean(food);
        lot.hidden = !lotAvailable;
        lot.querySelectorAll('input,button').forEach((field) => field.disabled = !lotAvailable);
    }

    initializeLine(line) {
        if (line.dataset.catalogInitialized) return;
        const foodSelect = line.querySelector('[data-line-food]');
        const selected = line.dataset.selectedFood || '';
        foodSelect.replaceChildren(
            new Option('Sélectionner une denrée', ''),
            ...this.catalog.denrees.map((food) => {
                const option = new Option(food.nom, food.id, false, food.id === selected);
                option.dataset.suppliers = food.fournisseurs.join(' ');
                return option;
            }),
        );
        try {
            line.packagingValues = JSON.parse(line.dataset.packagingValues || '{}');
        } catch {
            line.packagingValues = {};
        }
        line.dataset.catalogInitialized = 'true';
    }

    populateExitUnits(line, select, food) {
        const key = food || '';
        if (select.dataset.food === key) return;
        const selected = select.dataset.initialized ? select.value : (line.dataset.selectedExitUnit || '');
        const options = (this.catalog.sorties[food] || []).map((unit) => {
            const option = new Option(unit.nom, unit.id, false, unit.id === selected);
            option.dataset.symbol = unit.symbole;
            return option;
        });
        select.replaceChildren(new Option('Sélectionner un conditionnement', ''), ...options);
        select.dataset.food = key;
        select.dataset.initialized = 'true';
    }

    populateReferences(line, select, food, supplier, supplierEntry, inventoryMovement) {
        const active = supplierEntry || inventoryMovement;
        const mode = supplierEntry ? 'supplier' : (inventoryMovement ? 'inventory' : 'none');
        const key = active ? `${mode}:${food}:${supplierEntry ? supplier : ''}` : '';
        if (select.dataset.catalogKey === key) {
            if (!active) select.value = '';
            return;
        }
        const selected = select.dataset.initialized ? select.value : (line.dataset.selectedReference || '');
        const references = active
            ? this.catalog.references.filter((reference) => reference.denree === food && (!supplierEntry || reference.fournisseur === supplier))
            : [];
        select.replaceChildren(
            new Option(inventoryMovement ? 'Sélectionner un fournisseur' : 'Aucune référence', ''),
            ...references.map((reference) => new Option(reference.nom, reference.id, false, reference.id === selected)),
        );
        if (active && !select.value && (supplierEntry || references.length === 1)) select.value = references[0]?.id || '';
        select.dataset.catalogKey = key;
        select.dataset.initialized = 'true';
    }

    populatePackagings(line, referenceSelect, active) {
        line.querySelectorAll('[data-packaging-id]').forEach((input) => {
            line.packagingValues[input.dataset.packagingId] = input.value;
        });
        const container = line.querySelector('[data-line-packagings-container]');
        const reference = active
            ? this.catalog.references.find((candidate) => candidate.id === referenceSelect.value)
            : null;
        if (!reference?.conditionnements.length) {
            container.replaceChildren();
            return;
        }
        const block = document.createElement('div');
        block.className = 'movement-packagings';
        const title = document.createElement('h3');
        title.textContent = 'Quantités reçues par conditionnement';
        const grid = document.createElement('div');
        grid.className = 'movement-packaging-grid';
        const referenceSuffix = '[reference]';
        const prefix = referenceSelect.name.endsWith(referenceSuffix)
            ? referenceSelect.name.slice(0, -referenceSuffix.length)
            : '';
        grid.replaceChildren(...reference.conditionnements.map((packaging) => {
            const label = document.createElement('label');
            const name = document.createElement('span');
            name.textContent = packaging.libelle;
            const input = document.createElement('input');
            input.name = prefix
                ? `${prefix}[conditionnements][${packaging.id}]`
                : `conditionnements[${packaging.id}]`;
            input.value = line.packagingValues[packaging.id] || '';
            input.inputMode = 'decimal';
            input.min = '0';
            input.placeholder = '0';
            input.dataset.packagingId = packaging.id;
            const help = document.createElement('small');
            help.textContent = packaging.description;
            label.append(name, input, help);
            return label;
        }));
        block.append(title, grid);
        container.replaceChildren(block);
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
