(()=>{let theme='light';try{theme=localStorage.getItem('internpulse-theme')==='dark'?'dark':'light'}catch{}document.documentElement.dataset.theme=theme;document.addEventListener('DOMContentLoaded',()=>{const apply=()=>document.querySelectorAll('[data-theme-choice]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.themeChoice===document.documentElement.dataset.theme)));document.querySelectorAll('[data-theme-choice]').forEach(b=>b.onclick=()=>{document.documentElement.dataset.theme=b.dataset.themeChoice;try{localStorage.setItem('internpulse-theme',b.dataset.themeChoice)}catch{}apply()});apply();});})();
document.addEventListener('DOMContentLoaded',()=>{
 const menu=document.querySelector('.sidebar-disclosure');
 if(menu){const mobile=matchMedia('(max-width:760px)');const sync=()=>{menu.open=!mobile.matches};sync();mobile.addEventListener('change',sync);}
 const translateTheme=()=>{const lang=document.documentElement.lang==='ms'?'ms':'en';document.querySelectorAll('[data-theme-choice][data-en]').forEach(b=>b.textContent=b.dataset[lang]);};
 translateTheme();new MutationObserver(translateTheme).observe(document.documentElement,{attributes:true,attributeFilter:['lang']});
});
document.addEventListener('DOMContentLoaded',()=>{
 const layout=document.querySelector('.app-layout'), sidebar=document.querySelector('.workspace-sidebar'), fold=document.querySelector('.sidebar-fold');
 if(!layout||!sidebar||!fold)return;
 const paths=[
 'M3 10 12 3l9 7v11h-6v-7H9v7H3z',
 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M17 4a4 4 0 0 1 0 8M22 21v-2a4 4 0 0 0-3-3.87',
 'M3 21V3h12v18M15 9h6v12M7 7h4M7 11h4M7 15h4M1 21h22',
 'M9 5h12M9 12h12M9 19h12M3 4l1 1 2-2M3 11l1 1 2-2M3 18l1 1 2-2',
 'm9 12 2 2 4-4M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6z',
 'M4 4h16v17H4zM8 2v4M16 2v4M4 9h16M8 13h3M8 17h7',
 'M12 5C8 2 3 3 2 4v16c4-2 7-1 10 1 3-2 6-3 10-1V4c-1-1-6-2-10 1v16',
 'M4 21V10M10 21V4M16 21v-8M22 21H2',
 'M3 12a9 9 0 1 0 3-7M3 3v6h6M12 7v5l3 2'
 ];
 sidebar.querySelectorAll('.module-nav a').forEach((a,i)=>{
  const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('aria-hidden','true');svg.classList.add('nav-icon');
  const path=document.createElementNS(svg.namespaceURI,'path');path.setAttribute('d',paths[i]||paths[3]);svg.append(path);a.prepend(svg);
  const label=a.querySelector('[data-en]');a.setAttribute('aria-label',label?.textContent||a.textContent);a.title=a.textContent.trim();
  if(a.classList.contains('active'))a.setAttribute('aria-current','page');
 });
 let collapsed=false;try{collapsed=localStorage.getItem('internpulse-sidebar')==='collapsed'}catch{}
 const apply=()=>{layout.classList.toggle('sidebar-collapsed',collapsed);fold.setAttribute('aria-expanded',String(!collapsed));fold.setAttribute('aria-label',collapsed?'Expand sidebar / Buka sidebar':'Collapse sidebar / Lipat sidebar');fold.firstElementChild.textContent=collapsed?'›':'‹';};
 fold.onclick=()=>{collapsed=!collapsed;try{localStorage.setItem('internpulse-sidebar',collapsed?'collapsed':'expanded')}catch{}apply();};apply();
});