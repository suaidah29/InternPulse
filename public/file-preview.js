document.addEventListener('DOMContentLoaded', () => {
    const links = document.querySelectorAll('a[data-file-preview]');
    if (!links.length || typeof HTMLDialogElement === 'undefined') return;
    const dialog = document.createElement('dialog');
    dialog.className = 'file-preview-dialog';
    dialog.setAttribute('aria-labelledby', 'file-preview-title');
    dialog.innerHTML = '<div class="file-preview-header"><strong id="file-preview-title"></strong><div class="file-preview-actions"><a class="quiet-button" data-preview-download></a><button type="button" class="quiet-button" data-preview-close></button></div></div><iframe title="File preview" class="file-preview-frame"></iframe>';
    document.body.appendChild(dialog);
    const frame = dialog.querySelector('iframe');
    const title = dialog.querySelector('#file-preview-title');
    const download = dialog.querySelector('[data-preview-download]');
    const close = dialog.querySelector('[data-preview-close]');
    let opener;
    const translate = () => {
        const ms = document.documentElement.lang === 'ms';
        title.textContent = ms ? 'Paparan fail' : 'File preview';
        download.textContent = ms ? 'Muat turun' : 'Download';
        close.textContent = ms ? 'Tutup' : 'Close';
        frame.title = ms ? 'Paparan fail' : 'File preview';
    };
    links.forEach(link => link.addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        event.preventDefault();
        opener = link;
        translate();
        frame.src = link.href;
        const url = new URL(link.href, location.href);
        url.searchParams.set('download', '1');
        download.href = url.href;
        dialog.showModal();
    }));
    close.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { frame.src = 'about:blank'; opener?.focus(); });
});
