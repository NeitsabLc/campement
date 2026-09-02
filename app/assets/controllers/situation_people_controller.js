import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['select', 'list', 'empty'];

    connect() {
        this.refreshEmptyState();
    }

    add() {
        const option = this.selectTarget.selectedOptions[0];
        if (!option?.value || this.listTarget.querySelector(`[data-person-id="${CSS.escape(option.value)}"]`)) return;

        const item = document.createElement('li');
        item.dataset.personId = option.value;
        const identity = document.createElement('span');
        const [name, group = ''] = option.textContent.trim().split(' — ');
        const strong = document.createElement('strong');
        strong.textContent = name;
        const small = document.createElement('small');
        small.textContent = group;
        identity.append(strong, small);
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = 'Retirer';
        remove.addEventListener('click', () => this.removeItem(item));
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'participants[]';
        input.value = option.value;
        item.append(identity, remove, input);
        this.listTarget.append(item);

        option.hidden = true;
        option.selected = false;
        this.selectTarget.value = '';
        this.selectTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.refreshEmptyState();
    }

    remove(event) {
        this.removeItem(event.currentTarget.closest('[data-person-id]'));
    }

    removeItem(item) {
        if (!item) return;
        const option = [...this.selectTarget.options].find((candidate) => candidate.value === item.dataset.personId);
        if (option) option.hidden = false;
        item.remove();
        this.selectTarget.dispatchEvent(new Event('change', { bubbles: true }));
        this.refreshEmptyState();
    }

    refreshEmptyState() {
        this.emptyTarget.hidden = this.listTarget.children.length > 0;
    }
}
