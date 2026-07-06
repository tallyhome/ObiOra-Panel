<aside class="obiora-sidebar text-white p-3" style="width: 260px; min-height: 100vh; background: var(--obiora-sidebar-bg);">
    <div class="mb-4">
        <span class="fw-bold fs-5">ObiOra</span>
        <span class="badge bg-primary ms-1">Panel</span>
    </div>

    <nav class="nav flex-column gap-1">
        <a href="{{ route('dashboard') }}" class="nav-link text-white {{ request()->routeIs('dashboard') ? 'active bg-primary rounded' : '' }}">
            Dashboard
        </a>
        <a href="{{ route('servers.index') }}" class="nav-link text-white {{ request()->routeIs('servers.*') ? 'active bg-primary rounded' : '' }}">
            Serveurs
        </a>
        <a href="{{ route('services.index') }}" class="nav-link text-white {{ request()->routeIs('services.*') ? 'active bg-primary rounded' : '' }}">
            Services
        </a>
        <a href="{{ route('websites.index') }}" class="nav-link text-white {{ request()->routeIs('websites.*') ? 'active bg-primary rounded' : '' }}">
            Sites web
        </a>
        <a href="{{ route('databases.index') }}" class="nav-link text-white {{ request()->routeIs('databases.*') ? 'active bg-primary rounded' : '' }}">
            Bases de données
        </a>
        <a href="{{ route('docker.index') }}" class="nav-link text-white {{ request()->routeIs('docker.*') ? 'active bg-primary rounded' : '' }}">
            Docker
        </a>
        <a href="{{ route('backups.index') }}" class="nav-link text-white {{ request()->routeIs('backups.*') ? 'active bg-primary rounded' : '' }}">
            Sauvegardes
        </a>
    </nav>

    <div class="mt-auto pt-4 small text-secondary">
        v{{ config('obiora.version') }}
    </div>
</aside>
