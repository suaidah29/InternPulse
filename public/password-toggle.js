document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[type="password"]').forEach(input => {
        const wrapper = document.createElement('div');
        wrapper.className = 'password-field';
        input.before(wrapper);
        wrapper.append(input);
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-toggle';
        button.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m3 3 18 18"/></svg>';
        const sync = () => {
            const visible = input.type === 'text';
            const bm = document.documentElement.lang === 'ms';
            button.setAttribute('aria-label', visible ? (bm ? 'Sembunyikan kata laluan' : 'Hide password') : (bm ? 'Tunjukkan kata laluan' : 'Show password'));
            button.title = button.getAttribute('aria-label');
            button.setAttribute('aria-pressed', String(visible));
            button.querySelector('.eye-slash').style.display = visible ? '' : 'none';
        };
        button.addEventListener('click', () => { input.type = input.type === 'password' ? 'text' : 'password'; sync(); });
        input.form?.addEventListener('submit', () => { input.type = 'password'; sync(); });
        new MutationObserver(sync).observe(document.documentElement, { attributes: true, attributeFilter: ['lang'] });
        wrapper.append(button);
        sync();
    });
});
