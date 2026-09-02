import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['field', 'showIcon', 'hideIcon'];

    toggle(event) {
        const passwordIsVisible = this.fieldTarget.type === 'text';
        this.fieldTarget.type = passwordIsVisible ? 'password' : 'text';
        event.currentTarget.setAttribute('aria-pressed', passwordIsVisible ? 'false' : 'true');
        event.currentTarget.setAttribute('aria-label', passwordIsVisible ? 'Afficher le mot de passe' : 'Masquer le mot de passe');
        this.showIconTarget.hidden = !passwordIsVisible;
        this.hideIconTarget.hidden = passwordIsVisible;
        this.fieldTarget.focus({ preventScroll: true });
    }
}
