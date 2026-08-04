import { Controller } from '@hotwired/stimulus';

const MOBILE_QUERY = '(max-width: 48rem)';
const OPEN_RATIO = 0.4;

export default class extends Controller {
    static targets = ['surface', 'action'];
    static values = { url: String };

    connect() {
        this.open = false;
        this.dragging = false;
        this.ignoreClick = false;
        this.mediaQuery = window.matchMedia(MOBILE_QUERY);
        this.closeOtherRow = (event) => {
            if (event.detail !== this.element) this.setOpen(false);
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
        this.startOffset = this.open ? -this.actionWidth : 0;
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
        const offset = Math.max(-this.actionWidth, Math.min(0, this.startOffset + deltaX));
        this.translate(offset, false);
    }

    end(event) {
        if (this.startX === undefined) return;
        if (this.dragging) {
            const deltaX = event.clientX - this.startX;
            const offset = Math.max(-this.actionWidth, Math.min(0, this.startOffset + deltaX));
            this.setOpen(Math.abs(offset) >= this.actionWidth * OPEN_RATIO);
            this.ignoreClick = true;
            window.setTimeout(() => { this.ignoreClick = false; }, 0);
        }
        this.resetPointer();
    }

    cancel() {
        if (this.dragging) this.setOpen(this.open);
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
        if (this.mediaQuery.matches && this.open) {
            event.preventDefault();
            this.setOpen(false);
            return;
        }
        event.preventDefault();
        window.location.assign(this.urlValue);
    }

    get actionWidth() {
        return this.actionTarget.getBoundingClientRect().width || 104;
    }

    setOpen(open) {
        this.open = open && this.mediaQuery.matches;
        this.translate(this.open ? -this.actionWidth : 0, true);
        this.element.classList.toggle('foods-swipe-row--open', this.open);
        if (this.open) {
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
