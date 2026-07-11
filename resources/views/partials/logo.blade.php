{{-- Logo inline : ne dépend d'aucun fichier statique (SELinux, nginx, permissions). --}}
<a href="{{ $href ?? route('dashboard') }}" class="obiora-logo-link text-decoration-none d-inline-block" aria-label="ObiOra Panel SeedBox">
    <svg class="obiora-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 64" width="200" height="58" role="img" aria-hidden="true">
        <defs>
            <linearGradient id="obiora-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#3dd68c"/>
                <stop offset="100%" stop-color="#2ab872"/>
            </linearGradient>
            <linearGradient id="obiora-glow" x1="0%" y1="50%" x2="100%" y2="50%">
                <stop offset="0%" stop-color="#3dd68c" stop-opacity="0"/>
                <stop offset="50%" stop-color="#3dd68c" stop-opacity="0.35"/>
                <stop offset="100%" stop-color="#3dd68c" stop-opacity="0"/>
            </linearGradient>
        </defs>

        <g transform="translate(32 32)">
            <circle r="29" fill="#16161f" stroke="#2d2d42" stroke-width="1"/>
            <circle r="29" fill="none" stroke="url(#obiora-grad)" stroke-width="2" opacity="0.9"/>
            <ellipse cx="0" cy="0" rx="34" ry="8" fill="url(#obiora-glow)" transform="rotate(-12)"/>
            <circle r="13" fill="none" stroke="url(#obiora-grad)" stroke-width="2.5"/>
            <path d="M0 -19 v7" stroke="#3dd68c" stroke-width="2" stroke-linecap="round"/>
            <path d="M-3.5 -15.5 L0 -19 L3.5 -15.5" stroke="#3dd68c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
            <circle cx="17" cy="-4" r="2" fill="#3dd68c" opacity="0.85"/>
            <circle cx="-16" cy="6" r="2" fill="#6366f1" opacity="0.75"/>
            <path d="M11 -2 L-13 5" stroke="#3dd68c" stroke-width="1" opacity="0.35"/>
        </g>

        <text x="72" y="30" class="obiora-logo-text-main" font-family="Segoe UI, system-ui, -apple-system, sans-serif" font-size="21" font-weight="700" fill="#e8e8f0" letter-spacing="-0.5">ObiOra</text>
        <text x="154" y="30" class="obiora-logo-text-sub" font-family="Segoe UI, system-ui, -apple-system, sans-serif" font-size="13" font-weight="500" fill="#8b8ba3">Panel</text>
        <text x="72" y="48" font-family="Segoe UI, system-ui, -apple-system, sans-serif" font-size="10.5" font-weight="600" fill="url(#obiora-grad)" letter-spacing="0.32em">SEEDBOX</text>
        <rect x="72" y="52" width="42" height="2" rx="1" fill="url(#obiora-grad)" opacity="0.85"/>
    </svg>
</a>
