<aside class="obiora-sidebar text-white p-3" style="width: 240px; min-height: 100vh;">
    <div class="mb-4 pt-1">
        @include('partials.logo')
    </div>

    <nav class="nav flex-column gap-1 flex-grow-1">
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">▣</span> Dashboard
        </a>
        <a href="{{ route('plugins.index') }}" class="nav-link {{ request()->routeIs('plugins.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">◫</span> Marketplace
        </a>
        <a href="{{ route('services.index') }}" class="nav-link {{ request()->routeIs('services.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">⚙</span> Services
        </a>
        <a href="{{ route('servers.index') }}" class="nav-link {{ request()->routeIs('servers.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">⬢</span> Serveurs
        </a>
        @if(!empty($monitoringEnabled))
        <a href="{{ route('monitoring.index') }}" class="nav-link {{ request()->routeIs('monitoring.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">◉</span> Monitoring
            @if(!empty($realtimeEnabled))
                <span class="badge bg-primary ms-1" style="font-size:0.6rem;">WS</span>
            @endif
        </a>
        @endif
        <a href="{{ route('websites.index') }}" class="nav-link {{ request()->routeIs('websites.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">◎</span> Sites web
        </a>
        <a href="{{ route('databases.index') }}" class="nav-link {{ request()->routeIs('databases.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">▤</span> Bases de données
        </a>
        <a href="{{ route('docker.index') }}" class="nav-link {{ request()->routeIs('docker.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">⬡</span> Docker
        </a>
        <a href="{{ route('backups.index') }}" class="nav-link {{ request()->routeIs('backups.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">⬚</span> Sauvegardes
        </a>
        @if(!empty($stubModules))
        <hr class="border-secondary opacity-25 my-2">
        <div class="small text-muted px-2 mb-1">Infrastructure</div>
        @foreach($stubModules as $slug => $stub)
        <a href="{{ route('modules.stub', $slug) }}" class="nav-link small py-1 {{ request()->routeIs('modules.stub') && request()->route('slug') === $slug ? 'active' : '' }}">
            <span class="me-1">{{ $stub['icon'] ?? '◫' }}</span>{{ $stub['name'] ?? $slug }}
        </a>
        @endforeach
        @endif
        <hr class="border-secondary opacity-25 my-2">
        <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">◈</span> Licence & MAJ
            @if(!empty($updateAvailable))
                <span class="badge bg-warning text-dark ms-1">!</span>
            @endif
        </a>
    </nav>

    <div class="mt-auto pt-4 small text-muted">
        v{{ $panelVersion ?? config('obiora.version') }}
    </div>
</aside>
