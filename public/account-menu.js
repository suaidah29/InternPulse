document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('.account-menu');
    if (!menu) return;
    const trigger = menu.querySelector('summary');
    trigger.setAttribute('aria-expanded', String(menu.open));
    menu.addEventListener('toggle', () => trigger.setAttribute('aria-expanded', String(menu.open)));
    document.addEventListener('click', event => { if (!menu.contains(event.target)) menu.open = false; });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && menu.open) { menu.open = false; trigger.focus(); } });
    menu.addEventListener('focusout', () => setTimeout(() => { if (!menu.contains(document.activeElement)) menu.open = false; }, 0));
});
