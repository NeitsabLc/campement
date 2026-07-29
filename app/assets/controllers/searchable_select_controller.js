import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.select = this.element;
        this.select.classList.add('searchable-select__native');

        this.wrapper = document.createElement('div');
        this.wrapper.className = 'searchable-select';
        this.wrapper.innerHTML = `
            <button class="searchable-select__trigger" type="button" aria-haspopup="listbox" aria-expanded="false"></button>
            <div class="searchable-select__dropdown" popover="manual">
                <input class="searchable-select__search" type="search" placeholder="Rechercher une denrée…" aria-label="Rechercher une denrée" autocomplete="off">
                <div class="searchable-select__options" role="listbox"></div>
                <p class="searchable-select__empty" hidden>Aucune denrée trouvée</p>
            </div>`;
        this.select.insertAdjacentElement('afterend', this.wrapper);

        this.trigger = this.wrapper.querySelector('.searchable-select__trigger');
        this.dropdown = this.wrapper.querySelector('.searchable-select__dropdown');
        this.search = this.wrapper.querySelector('.searchable-select__search');
        this.options = this.wrapper.querySelector('.searchable-select__options');
        this.empty = this.wrapper.querySelector('.searchable-select__empty');

        this.isOpen = false;
        this.toggle = () => this.isOpen ? this.close() : this.open();
        this.filter = () => this.render();
        this.sync = () => { this.updateTrigger(); this.render(); };
        this.clickOutside = (event) => {
            if (!this.wrapper.contains(event.target)) this.close();
        };
        this.keydown = (event) => {
            if (event.key === 'Escape') {
                this.close();
                this.trigger.focus();
            }
        };
        this.reposition = () => {
            if (!this.isOpen) return;
            const trigger = this.trigger.getBoundingClientRect();
            this.dropdown.style.left = `${trigger.left}px`;
            this.dropdown.style.width = `${trigger.width}px`;
            this.dropdown.style.top = `${trigger.bottom + 4}px`;
            const dropdown = this.dropdown.getBoundingClientRect();
            if (dropdown.bottom > window.innerHeight - 8) {
                this.dropdown.style.top = `${Math.max(8, trigger.top - dropdown.height - 4)}px`;
            }
        };

        this.trigger.addEventListener('click', this.toggle);
        this.search.addEventListener('input', this.filter);
        this.wrapper.addEventListener('keydown', this.keydown);
        this.select.addEventListener('change', this.sync);
        document.addEventListener('click', this.clickOutside);
        window.addEventListener('resize', this.reposition);
        window.addEventListener('scroll', this.reposition, true);
        this.observer = new MutationObserver(this.sync);
        this.observer.observe(this.select, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden', 'disabled', 'selected'] });
        this.sync();
    }

    disconnect() {
        this.observer?.disconnect();
        document.removeEventListener('click', this.clickOutside);
        window.removeEventListener('resize', this.reposition);
        window.removeEventListener('scroll', this.reposition, true);
        this.select.classList.remove('searchable-select__native');
        this.wrapper?.remove();
    }

    open() {
        if (this.select.disabled) return;
        this.isOpen = true;
        this.dropdown.showPopover();
        this.trigger.setAttribute('aria-expanded', 'true');
        this.search.value = '';
        this.render();
        this.reposition();
        this.search.focus();
    }

    close() {
        if (!this.isOpen) return;
        this.isOpen = false;
        this.dropdown.hidePopover();
        this.trigger.setAttribute('aria-expanded', 'false');
    }

    updateTrigger() {
        const selected = this.select.selectedOptions[0];
        this.trigger.textContent = selected?.textContent.trim() || 'Sélectionner une denrée';
        this.trigger.classList.toggle('searchable-select__trigger--placeholder', !selected?.value);
        this.trigger.disabled = this.select.disabled;
    }

    render() {
        const query = this.search.value.trim().toLocaleLowerCase('fr');
        const available = [...this.select.options].filter((option) =>
            option.value && !option.hidden && !option.disabled &&
            option.textContent.toLocaleLowerCase('fr').includes(query)
        );

        this.options.replaceChildren(...available.map((option) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'searchable-select__option';
            button.textContent = option.textContent.trim();
            button.dataset.value = option.value;
            button.setAttribute('role', 'option');
            button.setAttribute('aria-selected', option.selected ? 'true' : 'false');
            button.addEventListener('click', () => {
                this.select.value = option.value;
                this.select.dispatchEvent(new Event('change', { bubbles: true }));
                this.close();
                this.trigger.focus();
            });
            return button;
        }));
        this.empty.hidden = available.length > 0;
    }
}
