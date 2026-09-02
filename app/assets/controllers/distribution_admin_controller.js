import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['link', 'copyButton', 'downloadButton', 'downloadStatus'];

    async copy() {
        await navigator.clipboard.writeText(this.linkTarget.value);
        const original = this.copyButtonTarget.textContent;
        this.copyButtonTarget.textContent = 'Copié !';
        window.setTimeout(() => this.copyButtonTarget.textContent = original, 1800);
    }

    async download(event) {
        event.preventDefault();

        const form = event.currentTarget;
        const original = this.downloadButtonTarget.textContent;
        this.downloadButtonTarget.disabled = true;
        this.downloadButtonTarget.textContent = 'Préparation…';
        this.downloadStatusTarget.hidden = false;
        this.downloadStatusTarget.classList.remove('flash--error', 'flash--success');
        this.downloadStatusTarget.classList.add('flash--info');
        this.downloadStatusTarget.textContent = 'Préparation de l’archive en cours…';

        try {
            const url = new URL(form.action, window.location.href);
            url.search = new URLSearchParams(new FormData(form)).toString();
            const response = await fetch(url, {headers: {Accept: 'application/zip'}});
            if (!response.ok || !response.headers.get('Content-Type')?.includes('application/zip')) {
                throw new Error('La génération de l’archive a échoué.');
            }

            const blobUrl = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = this.filename(response.headers.get('Content-Disposition'));
            document.body.append(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);

            form.closest('dialog')?.close();
            this.downloadStatusTarget.classList.replace('flash--info', 'flash--success');
            this.downloadStatusTarget.textContent = 'L’archive des fiches a bien été téléchargée.';
        } catch {
            this.downloadStatusTarget.classList.replace('flash--info', 'flash--error');
            this.downloadStatusTarget.textContent = 'Le téléchargement a échoué. Veuillez réessayer.';
        } finally {
            this.downloadButtonTarget.disabled = false;
            this.downloadButtonTarget.textContent = original;
        }
    }

    filename(disposition) {
        const match = disposition?.match(/filename="?([^";]+)"?/i);

        return match?.[1] ?? 'listes-courses.zip';
    }
}
