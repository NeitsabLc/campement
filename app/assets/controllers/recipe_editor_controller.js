import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
 static targets=['rows','template','catalog'];
 connect(){const table=this.element.querySelector('[data-public-count]');table.style.setProperty('--recipe-public-count',table.dataset.publicCount);this.catalog=JSON.parse(this.catalogTarget.textContent);this.refresh();}
 add(){this.rowsTarget.insertAdjacentHTML('beforeend',this.templateTarget.innerHTML);this.refresh();}
 remove(e){e.currentTarget.closest('.recipe-row').remove();this.refresh();}
 refresh(){[...this.rowsTarget.querySelectorAll('.recipe-row')].forEach((row,i)=>{const d=row.querySelector('[data-field="denree"]');const c=row.querySelector('[data-field="conditionnement"]');const r=row.querySelector('[data-field="regime"]');const selected=c.dataset.selected||c.value;c.replaceChildren(...(this.catalog[d.value]||[]).map(u=>new Option(u.nom,u.id,u.id===selected,u.id===selected)));delete c.dataset.selected;d.name=`lignes[${i}][denree]`;c.name=`lignes[${i}][conditionnement]`;r.name=`lignes[${i}][regime]`;row.querySelectorAll('[data-public]').forEach(x=>x.name=`lignes[${i}][quantites][${x.dataset.public}]`);});}
}
