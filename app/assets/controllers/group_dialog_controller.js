import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['adult', 'deleteDialog', 'deleteForm', 'deleteName', 'deleteToken', 'dialog', 'endDate', 'form', 'groupId', 'message', 'name', 'startDate', 'submit', 'title', 'type', 'young'];
    static values = { open: Boolean };

    connect() {
        this.messageTargets.forEach((message) => {
            window.setTimeout(() => {
                message.classList.add('flash--leaving');
                message.addEventListener('transitionend', () => message.remove(), { once: true });
                window.setTimeout(() => message.remove(), 300);
            }, 3000);
        });

        if (this.openValue) {
            this.open();
        }
    }

    openCreate() {
        this.formTarget.reset();
        this.groupIdTarget.value = '';
        this.nameTarget.value = '';
        this.youngTarget.value = '';
        this.adultTarget.value = '';
        this.startDateTarget.value = this.startDateTarget.min;
        this.endDateTarget.value = this.endDateTarget.max;
        this.typeTargets.forEach((input) => {
            input.checked = false;
        });
        this.titleTarget.textContent = 'Ajouter une unité';
        this.submitTarget.textContent = 'Créer l’unité';
        this.open();
    }

    openEdit(event) {
        const group = event.currentTarget.dataset;
        this.groupIdTarget.value = group.groupId;
        this.nameTarget.value = group.groupName;
        this.youngTarget.value = group.groupYoung;
        this.adultTarget.value = group.groupAdult;
        this.startDateTarget.value = group.groupStartDate;
        this.endDateTarget.value = group.groupEndDate;
        this.typeTargets.forEach((input) => {
            input.checked = input.value === group.groupType;
        });
        this.titleTarget.textContent = 'Modifier l’unité participante';
        this.submitTarget.textContent = 'Enregistrer les modifications';
        this.open();
    }

    open() {
        if (!this.dialogTarget.open) {
            this.dialogTarget.showModal();
        }
    }

    close() {
        this.dialogTarget.close();
    }

    closeOnBackdrop(event) {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    openDelete(event) {
        const group = event.currentTarget.dataset;
        this.deleteNameTarget.textContent = group.groupName;
        this.deleteFormTarget.action = group.deleteUrl;
        this.deleteTokenTarget.value = group.deleteToken;
        this.deleteDialogTarget.showModal();
    }

    closeDelete() {
        this.deleteDialogTarget.close();
    }

    closeDeleteOnBackdrop(event) {
        if (event.target === this.deleteDialogTarget) {
            this.closeDelete();
        }
    }

    submitFilter(event) {
        event.currentTarget.form.requestSubmit();
    }
}
