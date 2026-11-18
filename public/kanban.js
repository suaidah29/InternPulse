(()=>{
const root=document.querySelector('[data-kanban]');if(!root)return;
const live=root.querySelector('[data-kanban-live]');
const labels={todo:'To do',in_progress:'In progress',review:'For review',completed:'Completed'};
let dragging=null;
const refresh=()=>root.querySelectorAll('.kanban-col').forEach(col=>{
  const n=col.querySelectorAll('.kanban-card').length;
  col.querySelector('[data-count]').textContent=n;
  col.querySelector('.kanban-empty').hidden=n>0;
});
const say=m=>{if(live){live.textContent='';setTimeout(()=>live.textContent=m,30)}};
async function move(card,to){
  const from=card.dataset.status;
  if(from===to||to==='completed')return;
  const origin=card.parentElement,next=card.nextElementSibling;
  const list=root.querySelector('.kanban-col[data-status="'+to+'"] .kanban-list');
  list.insertBefore(card,list.querySelector('.kanban-empty'));
  card.dataset.status=to;card.classList.add('saving');
  const sel=card.querySelector('[data-move]');if(sel)sel.value=to;
  refresh();
  const fd=new FormData();fd.append('_token',root.dataset.csrf);fd.append('status',to);
  try{
    const r=await fetch(root.dataset.url+'/'+card.dataset.id+'/progress',{method:'POST',body:fd,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
    if(!r.ok)throw new Error(r.status);
    say((card.querySelector('.kanban-title').textContent)+' → '+labels[to]);
  }catch(e){
    origin.insertBefore(card,next&&next.parentElement===origin?next:origin.querySelector('.kanban-empty'));
    card.dataset.status=from;if(sel)sel.value=from;refresh();
    say('Could not move the task. It was put back in '+labels[from]+'.');
    card.classList.add('failed');setTimeout(()=>card.classList.remove('failed'),2500);
  }finally{card.classList.remove('saving')}
}
root.addEventListener('change',e=>{const s=e.target.closest('[data-move]');if(s)move(s.closest('.kanban-card'),s.value)});
root.addEventListener('dragstart',e=>{const c=e.target.closest('.kanban-card[data-mine]');if(!c){e.preventDefault();return}dragging=c;c.classList.add('dragging');e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',c.dataset.id)});
root.addEventListener('dragend',()=>{if(dragging)dragging.classList.remove('dragging');dragging=null;root.querySelectorAll('.drop-ok,.drop-no').forEach(x=>x.classList.remove('drop-ok','drop-no'))});
root.addEventListener('dragover',e=>{const z=e.target.closest('[data-dropzone]');if(!z||!dragging)return;const ok=!z.hasAttribute('data-locked');z.classList.toggle('drop-ok',ok);z.classList.toggle('drop-no',!ok);if(ok){e.preventDefault();e.dataTransfer.dropEffect='move'}});
root.addEventListener('dragleave',e=>{const z=e.target.closest('[data-dropzone]');if(z&&!z.contains(e.relatedTarget))z.classList.remove('drop-ok','drop-no')});
root.addEventListener('drop',e=>{const z=e.target.closest('[data-dropzone]');if(!z||!dragging||z.hasAttribute('data-locked'))return;e.preventDefault();const card=dragging;z.classList.remove('drop-ok','drop-no');move(card,z.closest('.kanban-col').dataset.status)});
})();
