import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['group', 'date', 'menu', 'menuBlock'];

    connect() {
        this.stampBrowserTime();
        const rememberedGroup = localStorage.getItem('campement.distribution.group');
        if (!this.groupTarget.value && rememberedGroup && [...this.groupTarget.options].some((o) => o.value === rememberedGroup)) {
            this.groupTarget.value = rememberedGroup;
        }
        this.refreshDates();
    }

    stampBrowserTime() {
        const now = new Date();
        const localTime = [now.getHours(), now.getMinutes(), now.getSeconds()].map((value) => String(value).padStart(2, '0')).join(':');
        this.element.querySelectorAll('[data-browser-datetime]').forEach((input) => input.value = now.toISOString());
        this.element.querySelectorAll('[data-browser-time]').forEach((input) => input.value = localTime);
        this.element.querySelectorAll('[data-browser-offset]').forEach((input) => input.value = String(now.getTimezoneOffset()));
    }

    rememberGroup() {
        localStorage.setItem('campement.distribution.group', this.groupTarget.value);
    }

    refreshDates() {
        const requested = this.dateTarget.dataset.selected;
        [...this.dateTarget.options].forEach((option) => option.hidden = false);
        if (requested && [...this.dateTarget.options].some((o) => o.value === requested)) this.dateTarget.value = requested;
        this.refreshMeals();
    }

    refreshMeals() {
        const date = this.dateTarget.value;
        const requested = this.menuTarget.dataset.selected;
        let first = '';
        [...this.menuTarget.options].forEach((option) => {
            const visible = !option.value || option.dataset.date === date;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible && option.value && !first) first = option.value;
        });
        this.menuTarget.value = requested && [...this.menuTarget.options].some((o) => o.value === requested && !o.disabled) ? requested : first;
        this.showMenu();
    }

    showMenu() {
        const selected = this.menuTarget.value;
        this.menuBlockTargets.forEach((block) => {
            const active = block.dataset.menuId === selected;
            block.hidden = !active;
            block.querySelectorAll('input').forEach((input) => input.disabled = !active);
        });
    }
}
