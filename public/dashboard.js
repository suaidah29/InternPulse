document.addEventListener('DOMContentLoaded',()=>{
 const menus=document.querySelectorAll('.header-settings,.notification-menu,.account-menu');
 menus.forEach(menu=>{
  const trigger=menu.querySelector('summary');
  menu.addEventListener('toggle',()=>{trigger.setAttribute('aria-expanded',String(menu.open));if(menu.open)menus.forEach(other=>{if(other!==menu)other.open=false;});});
  document.addEventListener('click',event=>{if(!menu.contains(event.target))menu.open=false;});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&menu.open){menu.open=false;trigger.focus();}});
  menu.addEventListener('focusout',()=>setTimeout(()=>{if(!menu.contains(document.activeElement))menu.open=false;},0));
 });
});
