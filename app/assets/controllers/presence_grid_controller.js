import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
 static targets=['comment','date','dateLabel','dialog','id','name','required','status'];
 open(e){const d=e.currentTarget.dataset;this.idTarget.value=d.id;this.dateTarget.value=d.date;this.nameTarget.textContent=d.name;this.dateLabelTarget.textContent=d.label;this.commentTarget.value=d.comment;this.statusTargets.forEach(x=>x.checked=x.value===d.status);this.updateRequired();this.dialogTarget.showModal();}
 connect(){this.statusTargets.forEach(x=>x.addEventListener('change',()=>this.updateRequired()));}
 updateRequired(){const departure=this.statusTargets.some(x=>x.checked&&x.value==='depart');this.commentTarget.required=departure;this.requiredTarget.textContent=departure?'*':'';}
 close(){this.dialogTarget.close();} backdrop(e){if(e.target===this.dialogTarget)this.close();}
}
