import { Controller } from '@hotwired/stimulus';

const MOBILE_QUERY = '(max-width: 48rem)';
const OPEN_RATIO = 0.4;

export default class extends Controller {
    static targets = ['surface', 'action', 'startAction'];
    static values = { url: String };

    connect() {
        this.openDirection = null;
        this.dragging = false;
        this.ignoreClick = false;
        this.mediaQuery = window.matchMedia(MOBILE_QUERY);
        this.closeOtherRow = (event) => {
            if (event.detail !== this.element) this.setOpen(null);
        };
        document.addEventListener('swipe-actions:open', this.closeOtherRow);
    }

    disconnect() {
        document.removeEventListener('swipe-actions:open', this.closeOtherRow);
    }

    start(event) {
        if (!this.mediaQuery.matches || event.button !== 0) return;
        this.startX = event.clientX;
        this.startY = event.clientY;
        this.startOffset = this.openDirection === 'end'
            ? -this.actionWidth
            : (this.openDirection === 'start' ? this.startActionWidth : 0);
        this.dragging = false;
    }

    move(event) {
        if (this.startX === undefined) return;
        const deltaX = event.clientX - this.startX;
        const deltaY = event.clientY - this.startY;
        if (!this.dragging) {
            if (Math.abs(deltaX) < 8) return;
            if (Math.abs(deltaY) >= Math.abs(deltaX)) {
                this.resetPointer();
                return;
            }
            this.dragging = true;
            this.surfaceTarget.setPointerCapture?.(event.pointerId);
        }

        event.preventDefault();
        const offset = Math.max(-this.actionWidth, Math.min(this.startActionWidth, this.startOffset + deltaX));
        this.translate(offset, false);
    }

    end(event) {
        if (this.startX === undefined) return;
        if (this.dragging) {
            const deltaX = event.clientX - this.startX;
            const offset = Math.max(-this.actionWidth, Math.min(this.startActionWidth, this.startOffset + deltaX));
            if (offset > 0 && this.startActionWidth > 0 && offset >= this.startActionWidth * OPEN_RATIO) {
                this.setOpen('start');
            } else if (offset < 0 && this.actionWidth > 0 && Math.abs(offset) >= this.actionWidth * OPEN_RATIO) {
                this.setOpen('end');
            } else {
                this.setOpen(null);
            }
            this.ignoreClick = true;
            window.setTimeout(() => { this.ignoreClick = false; }, 0);
        }
        this.resetPointer();
    }

    cancel() {
        if (this.dragging) this.setOpen(this.openDirection);
        this.resetPointer();
    }

    activate(event) {
        if (event.type === 'keydown' && !['Enter', ' '].includes(event.key)) return;
        if (event.target.closest('a, button, form, input')) return;
        if (this.ignoreClick) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        if (this.mediaQuery.matches && this.openDirection) {
            event.preventDefault();
            this.setOpen(null);
            return;
        }
        event.preventDefault();
        window.location.assign(this.urlValue);
    }

    get actionWidth() {
        return this.hasActionTarget ? (this.actionTarget.getBoundingClientRect().width || 104) : 0;
    }

    get startActionWidth() {
        return this.hasStartActionTarget ? (this.startActionTarget.getBoundingClientRect().width || 112) : 0;
    }

    setOpen(direction) {
        this.openDirection = this.mediaQuery.matches ? direction : null;
        const offset = this.openDirection === 'end'
            ? -this.actionWidth
            : (this.openDirection === 'start' ? this.startActionWidth : 0);
        this.translate(offset, true);
        this.element.classList.toggle('foods-swipe-row--open', Boolean(this.openDirection));
        if (this.openDirection) {
            document.dispatchEvent(new CustomEvent('swipe-actions:open', { detail: this.element }));
        }
    }

    translate(offset, animated) {
        this.surfaceTarget.classList.toggle('foods-row--dragging', !animated);
        this.surfaceTarget.style.transform = `translateX(${offset}px)`;
    }

    resetPointer() {
        this.startX = undefined;
        this.startY = undefined;
        this.dragging = false;
    }
}
