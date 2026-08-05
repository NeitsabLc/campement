import './stimulus_bootstrap.js';
document.addEventListener('input', (event) => {
    const search = event.target.closest('[data-food-catalog-target="search"]');
    if (!search) {
        return;
    }

    const query = search.value.trim().toLocaleLowerCase('fr');
    const catalog = search.closest('[data-controller~="food-catalog"]');
    catalog?.querySelectorAll('[data-food-catalog-target="row"]').forEach((row) => {
        row.classList.toggle('foods-row--filtered', !row.dataset.name.includes(query));
    });
});

const programmerDisparitionMessages = () => {
    document.querySelectorAll('.flash--success, [data-auto-dismiss]').forEach((message) => {
        if (message.dataset.dismissScheduled) return;
        message.dataset.dismissScheduled = 'true';
        window.setTimeout(() => {
            message.classList.add('flash--leaving');
            message.classList.add('confirmation-message--leaving');
            window.setTimeout(() => message.remove(), 250);
        }, 3000);
    });
};

document.addEventListener('DOMContentLoaded', programmerDisparitionMessages);
document.addEventListener('turbo:load', programmerDisparitionMessages);

const initialiserNavigation = () => {
    const body = document.body;
    body.classList.toggle('sidebar-collapsed', localStorage.getItem('campement-sidebar') === 'collapsed');
    document.querySelectorAll('[data-sidebar-collapse]').forEach((button) => button.addEventListener('click', () => {
        body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('campement-sidebar', body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
    }));
    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => button.addEventListener('click', () => body.classList.toggle('sidebar-open')));
    document.querySelectorAll('[data-sidebar-section]').forEach((section) => section.addEventListener('toggle', () => {
        if (!section.open) return;
        document.querySelectorAll('[data-sidebar-section]').forEach((otherSection) => {
            if (otherSection !== section) otherSection.open = false;
        });
    }));
    document.querySelectorAll('[data-open-dialog]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.openDialog)?.showModal()));
    document.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
};
document.addEventListener('DOMContentLoaded', initialiserNavigation);
document.addEventListener('turbo:load', initialiserNavigation);

// Affiche les erreurs de la validation HTML au plus près du champ. Les écouteurs
// sont délégués afin de couvrir également les lignes et formulaires ajoutés en JS.
const SELECTEUR_CHAMP_VALIDABLE = 'input:not([type="hidden"]), select, textarea';

const messageValidation = (champ) => {
    const validite = champ.validity;

    if (validite.valueMissing) return 'Ce champ est obligatoire.';
    if (validite.typeMismatch && champ.type === 'email') return 'Saisissez une adresse électronique valide.';
    if (validite.typeMismatch && champ.type === 'url') return 'Saisissez une adresse web valide.';
    if (validite.patternMismatch) return champ.dataset.validationPattern || 'Le format saisi n’est pas valide.';
    if (validite.tooShort) return `Saisissez au moins ${champ.minLength} caractères.`;
    if (validite.tooLong) return `Saisissez au maximum ${champ.maxLength} caractères.`;
    if (validite.rangeUnderflow) return `La valeur minimale autorisée est ${champ.min}.`;
    if (validite.rangeOverflow) return `La valeur maximale autorisée est ${champ.max}.`;
    if (validite.stepMismatch) return 'Saisissez une valeur autorisée.';
    if (validite.badInput) return 'Saisissez une valeur valide.';

    return champ.validationMessage || 'La valeur saisie n’est pas valide.';
};

const conteneurErreur = (champ) => champ.closest('label, .form-field, .group-form-field, .user-field') || champ.parentElement;

const retirerErreurValidation = (champ) => {
    const identifiant = champ.getAttribute('aria-errormessage');
    if (identifiant) document.getElementById(identifiant)?.remove();
    champ.removeAttribute('aria-invalid');
    champ.removeAttribute('aria-errormessage');
};

const afficherErreurValidation = (champ) => {
    if (!(champ instanceof HTMLInputElement || champ instanceof HTMLSelectElement || champ instanceof HTMLTextAreaElement)) return;
    if (champ.validity.valid || champ.disabled) {
        retirerErreurValidation(champ);
        return;
    }

    let identifiant = champ.getAttribute('aria-errormessage');
    let erreur = identifiant ? document.getElementById(identifiant) : null;
    if (!erreur) {
        identifiant = `validation-${crypto.randomUUID()}`;
        erreur = document.createElement('span');
        erreur.id = identifiant;
        erreur.className = 'field-validation-error';
        erreur.setAttribute('role', 'alert');
        conteneurErreur(champ)?.append(erreur);
    }

    erreur.textContent = messageValidation(champ);
    champ.setAttribute('aria-invalid', 'true');
    champ.setAttribute('aria-errormessage', identifiant);
};

document.addEventListener('invalid', (event) => {
    const champ = event.target.closest?.(SELECTEUR_CHAMP_VALIDABLE);
    if (!champ) return;
    event.preventDefault();
    afficherErreurValidation(champ);

    // Une seule programmation par formulaire suffit, même si plusieurs champs
    // déclenchent successivement l’événement « invalid ».
    const formulaire = champ.form;
    if (formulaire && !formulaire.dataset.validationFocusScheduled) {
        formulaire.dataset.validationFocusScheduled = 'true';
        window.requestAnimationFrame(() => {
            const premierChampInvalide = formulaire.querySelector(':invalid');
            premierChampInvalide?.focus({preventScroll: true});
            premierChampInvalide?.scrollIntoView({behavior: 'smooth', block: 'center'});
            delete formulaire.dataset.validationFocusScheduled;
        });
    }
}, true);

const actualiserErreurValidation = (event) => {
    const champ = event.target.closest?.(SELECTEUR_CHAMP_VALIDABLE);
    if (!champ || !champ.hasAttribute('aria-invalid')) return;
    afficherErreurValidation(champ);
};

document.addEventListener('input', actualiserErreurValidation);
document.addEventListener('change', actualiserErreurValidation);

// Signale les erreurs de format dès la sortie du champ. Un champ obligatoire
// encore vide reste silencieux jusqu'à l'envoi du formulaire.
document.addEventListener('focusout', (event) => {
    const champ = event.target.closest?.(SELECTEUR_CHAMP_VALIDABLE);
    if (!champ || champ.value === '') return;
    afficherErreurValidation(champ);
});
