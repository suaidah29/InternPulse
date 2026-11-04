document.addEventListener('DOMContentLoaded', () => {
 const password=document.getElementById('new-password'), confirm=document.getElementById('confirm-password');
 if(password && confirm){
  const list=document.createElement('ul');list.className='password-checklist';list.setAttribute('aria-label','Password requirements / Syarat kata laluan');
  const rules=[['12+ characters','12+ aksara',v=>v.length>=12],['Uppercase letter','Huruf besar',v=>/[A-Z]/.test(v)],['Lowercase letter','Huruf kecil',v=>/[a-z]/.test(v)],['Number','Nombor',v=>/[0-9]/.test(v)],['Symbol','Simbol',v=>/[^a-zA-Z0-9\s]/.test(v)],['Passwords match','Kata laluan sepadan',v=>!!v && v===confirm.value]];
  rules.forEach(([en,ms])=>{const item=document.createElement('li');const label=document.createElement('span');label.dataset.en=en;label.dataset.ms=ms;label.textContent=document.documentElement.lang==='ms'?ms:en;item.append(label);list.append(item)});
  confirm.closest('.password-field')?.after(list);if(!list.isConnected)confirm.after(list);
  const update=()=>{rules.forEach((r,i)=>list.children[i].classList.toggle('met',r[2](password.value)));confirm.setCustomValidity(confirm.value && confirm.value!==password.value?'Passwords must match / Kata laluan mesti sepadan':'');};
  password.addEventListener('input',update);confirm.addEventListener('input',update);update();
 }
 document.querySelectorAll('select[name="assigned_to"]').forEach(select=>{
  const search=document.createElement('input');search.type='search';search.placeholder='Search name, email or department / Cari nama, e-mel atau jabatan';search.setAttribute('aria-label',search.placeholder);search.className='assignee-search';
  select.before(search);const options=Array.from(select.options).map(o=>o.cloneNode(true));
  search.addEventListener('input',()=>{const selected=select.value;const term=search.value.toLowerCase();select.replaceChildren(...options.filter(o=>!o.value || o.value===selected || o.textContent.toLowerCase().includes(term)).map(o=>o.cloneNode(true)));select.value=selected;});
 });
 document.querySelectorAll('[data-log-suggestion]').forEach(button=>button.addEventListener('click',()=>{const field=button.closest('form').querySelector('[name="points"]');const value=button.dataset.logSuggestion;if(!field.value.split('\n').includes(value))field.value+=(field.value?'\n':'')+value;field.focus();}));
});
