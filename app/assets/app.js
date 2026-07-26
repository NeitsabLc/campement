import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
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

console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

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
    document.querySelectorAll('[data-open-dialog]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.openDialog)?.showModal()));
    document.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
};
document.addEventListener('DOMContentLoaded', initialiserNavigation);
document.addEventListener('turbo:load', initialiserNavigation);
