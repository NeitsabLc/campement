import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['address', 'deleteDialog', 'deleteForm', 'deleteName', 'deleteToken', 'dialog', 'email', 'form', 'message', 'name', 'phone', 'submit', 'supplierId', 'title'];
    static values = { open: Boolean };

    connect() {
        this.messageTargets.forEach((message) => {
            window.setTimeout(() => {
                message.classList.add('flash--leaving');
                message.addEventListener('transitionend', () => message.remove(), { once: true });
                window.setTimeout(() => message.remove(), 300);
            }, 3000);
        });
        if (this.openValue) this.open();
    }

    openCreate() {
        this.formTarget.reset();
        this.supplierIdTarget.value = '';
        this.titleTarget.textContent = 'Ajouter un fournisseur';
        this.submitTarget.textContent = 'Créer le fournisseur';
        this.open();
    }

    openEdit(event) {
        const supplier = event.currentTarget.dataset;
        this.supplierIdTarget.value = supplier.supplierId;
        this.nameTarget.value = supplier.supplierName;
        this.phoneTarget.value = supplier.supplierPhone;
        this.emailTarget.value = supplier.supplierEmail;
        this.addressTarget.value = supplier.supplierAddress;
        this.titleTarget.textContent = 'Modifier le fournisseur';
        this.submitTarget.textContent = 'Enregistrer les modifications';
        this.open();
    }

    open() { if (!this.dialogTarget.open) this.dialogTarget.showModal(); }
    close() { this.dialogTarget.close(); }
    closeOnBackdrop(event) { if (event.target === this.dialogTarget) this.close(); }

    openDelete(event) {
        const supplier = event.currentTarget.dataset;
        this.deleteNameTarget.textContent = supplier.supplierName;
        this.deleteFormTarget.action = supplier.deleteUrl;
        this.deleteTokenTarget.value = supplier.deleteToken;
        this.deleteDialogTarget.showModal();
    }

    closeDelete() { this.deleteDialogTarget.close(); }
    closeDeleteOnBackdrop(event) { if (event.target === this.deleteDialogTarget) this.closeDelete(); }
    submitFilter(event) { event.currentTarget.form.requestSubmit(); }
}
