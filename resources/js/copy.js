/**
 * Copie texte — fonctionne en HTTP (sans navigator.clipboard sécurisé).
 */
export function obioraCopyText(text) {
    const value = String(text ?? '').trim();

    if (value === '') {
        return Promise.resolve(false);
    }

    const done = () => {
        if (typeof window.obioraToast === 'function') {
            window.obioraToast('success', 'Copié dans le presse-papiers');
        }
    };

    if (navigator.clipboard?.writeText) {
        return navigator.clipboard.writeText(value).then(() => {
            done();
            return true;
        }).catch(() => fallbackCopy(value, done));
    }

    return Promise.resolve(fallbackCopy(value, done));
}

export function obioraCopyFromButton(button) {
    const block = button?.closest?.('.obiora-copy-block');

    if (!block) {
        return Promise.resolve(false);
    }

    const pre = block.querySelector('.obiora-copy-text');

    return obioraCopyText(pre?.textContent ?? '');
}

function fallbackCopy(text, onSuccess) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.top = '-1000px';
    textarea.style.left = '-1000px';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    let ok = false;

    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }

    document.body.removeChild(textarea);

    if (ok) {
        onSuccess();
    } else if (typeof window.obioraToast === 'function') {
        window.obioraToast('warning', 'Copie impossible — sélectionnez le texte manuellement (Ctrl+C)');
    }

    return ok;
}
