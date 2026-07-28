import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
 static targets=['rows','template','catalog'];
 connect(){this.catalog=JSON.parse(this.catalogTarget.textContent);this.refresh();}
 add(){this.rowsTarget.insertAdjacentHTML('beforeend',this.templateTarget.innerHTML);this.refresh();}
 remove(e){e.currentTarget.closest('.recipe-row').remove();this.refresh();}
 refresh(){[...this.rowsTarget.querySelectorAll('.recipe-row')].forEach((row,i)=>{const d=row.querySelector('[data-field="denree"]');const c=row.querySelector('[data-field="conditionnement"]');const selected=c.dataset.selected||c.value;c.innerHTML=(this.catalog[d.value]||[]).map(u=>`<option value="${u.id}" ${u.id===selected?'selected':''}>${u.nom}</option>`).join('');delete c.dataset.selected;d.name=`lignes[${i}][denree]`;c.name=`lignes[${i}][conditionnement]`;row.querySelectorAll('[data-public]').forEach(x=>x.name=`lignes[${i}][quantites][${x.dataset.public}]`);});}
}
