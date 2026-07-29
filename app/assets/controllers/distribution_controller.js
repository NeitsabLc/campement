import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['group', 'date', 'menu', 'menuBlock', 'regularSelectors', 'specialMeal', 'portion'];

    connect() {
        this.stampBrowserTime();
        this.defaultMealCode = this.mealCodeForCurrentTime(new Date());
        const rememberedGroup = localStorage.getItem('campement.distribution.group');
        if (!this.groupTarget.value && rememberedGroup && [...this.groupTarget.options].some((o) => o.value === rememberedGroup)) {
            this.groupTarget.value = rememberedGroup;
        }
        this.refreshPortions();
        this.refreshDates();
        this.refreshSpecialMeal();
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
        this.refreshPortions();
    }

    refreshPortions() {
        const groupType = this.groupTarget.selectedOptions[0]?.dataset.groupType;
        const publicCode = groupType?.toUpperCase().replaceAll('-', '_');
        const visibleCodes = new Set(publicCode ? [publicCode, 'ADULTE'] : []);

        this.portionTargets.forEach((portion) => {
            portion.hidden = visibleCodes.size > 0 && !visibleCodes.has(portion.dataset.publicCode);
        });
    }

    refreshDates() {
        const requested = this.dateTarget.dataset.selected;
        const now = new Date();
        const today = [
            now.getFullYear(),
            String(now.getMonth() + 1).padStart(2, '0'),
            String(now.getDate()).padStart(2, '0'),
        ].join('-');
        [...this.dateTarget.options].forEach((option) => option.hidden = false);
        if (requested && [...this.dateTarget.options].some((o) => o.value === requested)) {
            this.dateTarget.value = requested;
        } else if ([...this.dateTarget.options].some((o) => o.value === today)) {
            this.dateTarget.value = today;
        }
        this.refreshMeals();
        this.defaultMealCode = null;
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
        const requestedOption = [...this.menuTarget.options].find((option) => option.value === requested && !option.disabled);
        let defaultOption = [...this.menuTarget.options].find(
            (option) => option.dataset.mealCode === this.defaultMealCode && !option.disabled,
        );
        if (!defaultOption && this.defaultMealCode === 'GOUTER') {
            defaultOption = [...this.menuTarget.options].find(
                (option) => option.dataset.mealCode === 'DINER' && !option.disabled,
            );
        }
        this.menuTarget.value = requestedOption?.value || defaultOption?.value || first;
        this.showMenu();
    }

    mealCodeForCurrentTime(now) {
        const hour = now.getHours();
        if (hour < 10) return 'PETIT_DEJEUNER';
        if (hour < 13) return 'DEJEUNER';
        if (hour < 16) return 'GOUTER';
        if (hour < 20) return 'DINER';
        return null;
    }

    selectSpecialMeal(event) {
        if (event.currentTarget.checked) {
            this.specialMealTargets.forEach((checkbox) => {
                if (checkbox !== event.currentTarget) checkbox.checked = false;
            });
        }
        this.refreshSpecialMeal();
    }

    refreshSpecialMeal() {
        const selected = this.specialMealTargets.find((checkbox) => checkbox.checked);
        this.regularSelectorsTarget.hidden = Boolean(selected);
        this.menuTarget.disabled = Boolean(selected);
        this.dateTarget.disabled = Boolean(selected);

        if (selected) {
            this.showMenu(selected.value);
        } else {
            this.showMenu();
        }
    }

    showMenu(menuId) {
        const selected = typeof menuId === 'string' ? menuId : this.menuTarget.value;
        this.menuBlockTargets.forEach((block) => {
            const active = block.dataset.menuId === selected;
            block.hidden = !active;
            block.querySelectorAll('input').forEach((input) => input.disabled = !active);
        });
    }
}
