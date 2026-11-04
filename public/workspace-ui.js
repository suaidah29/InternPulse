document.addEventListener('DOMContentLoaded',()=>{
 const foldSection=(element,en,ms)=>{
  if(!element)return;
  const detail=document.createElement('details');detail.className='ui-secondary';
  const summary=document.createElement('summary');const label=document.createElement('span');label.dataset.en=en;label.dataset.ms=ms;label.textContent=document.documentElement.lang==='ms'?ms:en;summary.append(label);
  element.before(detail);detail.append(summary,element);
  if(element.querySelector('.notice.warning')||location.hash==='#'+element.id||element.querySelector('#import-preview'))detail.open=true;
 };
 foldSection(document.getElementById('supervisors'),'Supervisor directory','Senarai penyelia');
 foldSection(document.getElementById('invitations'),'Invitations · view status and resend','Jemputan · lihat status dan hantar semula');
 const bulk=document.getElementById('import');foldSection(bulk?.closest('.lower-grid'),'Bulk invitations & recent activity','Jemputan pukal & aktiviti terkini');
 const openTarget=()=>{const target=document.getElementById(location.hash.slice(1));if(target){let p=target.parentElement;while(p){if(p.tagName==='DETAILS')p.open=true;p=p.parentElement;}}};window.addEventListener('hashchange',openTarget);openTarget();
 document.querySelectorAll('select[name="assigned_to"]').forEach(select=>{
  if(select.querySelector('option[value=""]') || !select.closest('form')?.action.endsWith('/tasks'))return;
  const option=new Option('Choose a person / Pilih individu','',true,true);option.disabled=true;select.prepend(option);
 });
 document.querySelectorAll('.module-nav a').forEach(a=>{const label=a.querySelector('[data-en]');if(!label)return;const sync=()=>{a.title=label.textContent;a.setAttribute('aria-label',label.textContent)};sync();new MutationObserver(sync).observe(label,{childList:true,subtree:true,characterData:true});});
});
