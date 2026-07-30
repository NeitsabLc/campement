import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['adult', 'adultInfo', 'deleteDialog', 'deleteForm', 'deleteName', 'deleteToken', 'dialog', 'form', 'group', 'other', 'title', 'type', 'young'];
    static values = { open: Boolean };

    connect() { if (this.openValue) { this.update(); this.dialogTarget.showModal(); } }

    formTargetConnected(form) {
        form.querySelectorAll('input').forEach((input) => {
            input.addEventListener('blur', () => this.validateInput(input));
            input.addEventListener('invalid', (event) => {
                event.preventDefault();
                this.validateInput(input);
            });
        });
        form.addEventListener('submit', (event) => {
            this.validateDates();
            const invalidInputs = [...form.querySelectorAll('input:not(:disabled)')].filter((input) => !input.checkValidity());
            invalidInputs.forEach((input) => this.validateInput(input));
            if (invalidInputs.length > 0) {
                event.preventDefault();
                invalidInputs[0].focus();
            }
        });
    }
    open(event) {
        this.formTarget.reset();
        this.clearValidation();
        this.typeTarget.value = event.currentTarget.dataset.participantType;
        this.groupTarget.value = event.currentTarget.dataset.participantGroup;
        this.titleTarget.textContent = this.typeTarget.value === 'jeune' ? 'Ajouter un jeune' : 'Ajouter un adulte';
        this.update(); this.dialogTarget.showModal();
    }
    close() { this.dialogTarget.close(); }
    backdrop(event) { if (event.target === this.dialogTarget) this.close(); }
    openDelete(event) {
        const participant = event.currentTarget.dataset;
        this.deleteNameTarget.textContent = participant.participantName;
        this.deleteFormTarget.action = participant.deleteUrl;
        this.deleteTokenTarget.value = participant.deleteToken;
        this.deleteDialogTarget.showModal();
    }
    closeDelete() { this.deleteDialogTarget.close(); }
    deleteBackdrop(event) { if (event.target === this.deleteDialogTarget) this.closeDelete(); }
    qualification() { this.updateOther(); }
    update() {
        const jeune = this.typeTarget.value === 'jeune';
        this.youngTarget.hidden = !jeune; this.adultTarget.hidden = jeune;
        this.adultInfoTarget.hidden = jeune;
        this.youngTarget.querySelectorAll('input').forEach(input => input.disabled = !jeune);
        this.adultTarget.querySelectorAll('input, textarea').forEach(input => input.disabled = jeune);
        this.adultInfoTarget.querySelectorAll('input').forEach(input => input.disabled = jeune);
        this.formTarget.querySelectorAll(':disabled').forEach((input) => {
            input.removeAttribute('aria-invalid');
            input.closest('label')?.querySelector('.participant-field-error')?.remove();
        });
        this.updateOther();
    }
    updateOther() {
        const input = this.adultTarget.querySelector('input[value="Autre diplôme"]');
        this.otherTarget.hidden = !input?.checked;
        this.otherTarget.querySelector('input').disabled = !input?.checked;
    }

    validateDates() {
        const start = this.formTarget.querySelector('[name="date_debut_presence"]');
        const end = this.formTarget.querySelector('[name="date_fin_presence"]');
        end.setCustomValidity(start.value && end.value && end.value < start.value ? 'La date de fin doit être postérieure ou égale à la date de début.' : '');
        this.validateInput(start);
        this.validateInput(end);
    }

    validateInput(input) {
        if (input.type === 'tel') {
            const phonePattern = /^(?:0[1-9](?:[ .-]?\d{2}){4}|\+33[ .-]?[1-9](?:[ .-]?\d{2}){4})$/;
            input.setCustomValidity(input.value && !phonePattern.test(input.value)
                ? 'Saisissez un numéro français valide, par exemple 06 12 34 56 78 ou +33 6 12 34 56 78.'
                : '');
        }
        if (input.name === 'date_debut_presence' || input.name === 'date_fin_presence') {
            const start = this.formTarget.querySelector('[name="date_debut_presence"]');
            const end = this.formTarget.querySelector('[name="date_fin_presence"]');
            end.setCustomValidity(start.value && end.value && end.value < start.value ? 'La date de fin doit être postérieure ou égale à la date de début.' : '');
        }

        const label = input.closest('label');
        if (!label || input.disabled || ['checkbox', 'hidden'].includes(input.type)) return;
        let error = label.querySelector('.participant-field-error');
        const message = this.errorMessage(input);
        if (!message) {
            error?.remove();
            input.removeAttribute('aria-invalid');
            return;
        }
        if (!error) {
            error = document.createElement('span');
            error.className = 'participant-field-error';
            error.setAttribute('role', 'alert');
            label.append(error);
        }
        error.textContent = message;
        input.setAttribute('aria-invalid', 'true');
    }

    errorMessage(input) {
        if (input.validity.valid) return '';
        if (input.validity.valueMissing) return 'Ce champ est obligatoire.';
        if (input.validity.typeMismatch && input.type === 'email') return 'Saisissez une adresse e-mail valide.';
        if (input.validity.patternMismatch && input.type === 'tel') return 'Saisissez un numéro français valide, par exemple 06 12 34 56 78 ou +33 6 12 34 56 78.';
        if (input.validity.rangeUnderflow || input.validity.rangeOverflow) return 'La date doit être comprise dans les dates du séjour.';
        if (input.validity.tooLong) return `Ce champ ne peut pas dépasser ${input.maxLength} caractères.`;
        if (input.validity.customError) return input.validationMessage;
        return 'La valeur saisie est invalide.';
    }

    clearValidation() {
        this.formTarget.querySelectorAll('input').forEach((input) => {
            input.setCustomValidity('');
            input.removeAttribute('aria-invalid');
        });
        this.formTarget.querySelectorAll('.participant-field-error').forEach((error) => error.remove());
    }
}
