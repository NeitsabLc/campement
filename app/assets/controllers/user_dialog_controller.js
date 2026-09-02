import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.abortController = new AbortController();
        const listenerOptions = { signal: this.abortController.signal };
        const selector = this.element.querySelector('[data-role-selector]');
        const dialog = this.element.querySelector('[data-user-dialog]');
        const groupStaySelector = this.element.querySelector('[data-group-stay-selector]');
        const groupSelector = this.element.querySelector('[data-group-selector]');
        const form = dialog?.querySelector('form');
        const update = () => this.element.querySelectorAll('[data-role-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.rolePanel !== selector.value;
        });
        const updateGroups = () => {
            if (!groupSelector || !groupStaySelector) return;
            const selectedStay = groupStaySelector.value;
            Array.from(groupSelector.options).forEach((option, index) => {
                if (index === 0) return;
                option.hidden = option.dataset.stayId !== selectedStay;
                option.disabled = option.hidden;
            });
            if (groupSelector.selectedOptions[0]?.disabled) groupSelector.value = '';
        };
        selector?.addEventListener('change', update, listenerOptions);
        groupStaySelector?.addEventListener('change', updateGroups, listenerOptions);
        update();
        updateGroups();
        if (!dialog) return;

        const openCreate = () => {
            form?.reset();
            dialog.querySelector('[data-user-id-field]').value = '';
            dialog.querySelector('[data-user-dialog-title]').textContent = 'Ajouter un utilisateur';
            dialog.querySelector('[data-user-dialog-intro]').textContent = 'Un lien d’invitation lui sera envoyé par e-mail.';
            dialog.querySelector('[data-user-submit]').textContent = 'Créer et envoyer l’invitation';
            update();
            updateGroups();
            dialog.showModal();
        };

        this.element.querySelector('[data-user-dialog-open]')?.addEventListener('click', openCreate, listenerOptions);
        this.element.querySelector('[data-user-dialog-close]')?.addEventListener('click', () => dialog.close(), listenerOptions);
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        }, listenerOptions);

        const statusDialog = this.element.querySelector('[data-user-status-dialog]');
        const statusForm = statusDialog?.querySelector('[data-user-disable-form]');
        this.element.querySelectorAll('[data-user-disable]').forEach((button) => button.addEventListener('click', () => {
            statusDialog.querySelector('[data-user-disable-name]').textContent = button.dataset.userName;
            statusDialog.querySelector('[data-user-disable-token]').value = button.dataset.statusToken;
            statusForm.action = button.dataset.statusUrl;
            statusDialog.showModal();
        }, listenerOptions));
        statusDialog?.querySelector('[data-user-disable-cancel]')?.addEventListener('click', () => statusDialog.close(), listenerOptions);
        statusDialog?.addEventListener('click', (event) => {
            if (event.target === statusDialog) statusDialog.close();
        }, listenerOptions);

        update();
        updateGroups();
        if (dialog.hasAttribute('data-open-on-load')) dialog.showModal();
    }

    disconnect() {
        this.abortController?.abort();
    }
}
