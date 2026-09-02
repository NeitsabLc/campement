import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        const formatter = new Intl.DateTimeFormat('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

        this.element.querySelectorAll('time[datetime]').forEach((element) => {
            const date = new Date(element.dateTime);
            if (!Number.isNaN(date.getTime())) element.textContent = formatter.format(date).replace(',', '');
        });
    }
}
