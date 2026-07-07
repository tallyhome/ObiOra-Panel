function initInfrastructureToggle() {
    const toggle = document.getElementById('infra-toggle');
    const panel = document.getElementById('infra-nav');
    if (!toggle || !panel) {
        return;
    }

    const storageKey = 'obiora.sidebar.infrastructure';
    const forceOpen = toggle.dataset.forceOpen === '1';

    const setOpen = (open) => {
        panel.classList.toggle('show', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.querySelector('.infra-chevron')?.classList.toggle('is-open', open);
        try {
            localStorage.setItem(storageKey, open ? '1' : '0');
        } catch {
            /* ignore */
        }
    };

    let open = forceOpen;
    if (!forceOpen) {
        try {
            open = localStorage.getItem(storageKey) === '1';
        } catch {
            open = false;
        }
    }
    setOpen(open);

    toggle.addEventListener('click', () => {
        setOpen(!panel.classList.contains('show'));
    });
}

document.addEventListener('DOMContentLoaded', initInfrastructureToggle);
