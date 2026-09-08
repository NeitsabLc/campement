import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
 static targets = ['search', 'row'];
 filter() { const query = this.searchTarget.value.trim().toLocaleLowerCase('fr'); this.rowTargets.forEach(row => { row.classList.toggle('foods-row--filtered', !row.dataset.name.includes(query)); }); }
}
