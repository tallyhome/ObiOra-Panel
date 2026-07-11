<aside class="obiora-sidebar p-3" style="width: 240px; min-height: 100vh;">
    <div class="mb-4 pt-1">
        @include('partials.logo')
    </div>

    <nav class="nav flex-column gap-1 flex-grow-1">
        @can('dashboard.view')
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">▣</span> {{ __('panel.nav.dashboard') }}
        </a>
        @endcan
        @can('plugins.view')
        <a href="{{ route('plugins.index') }}" class="nav-link {{ request()->routeIs('plugins.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">◫</span> {{ __('panel.nav.marketplace') }}
        </a>
        @endcan
        @can('services.view')
        <a href="{{ route('services.index') }}" class="nav-link {{ request()->routeIs('services.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">⚙</span> {{ __('panel.nav.services') }}
        </a>
        @endcan
        @can('servers.view')
        <a href="{{ route('servers.index') }}" class="nav-link {{ request()->routeIs('servers.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">⬢</span> {{ __('panel.nav.servers') }}
        </a>
        @endcan
        @can('monitoring.view')
        <a href="{{ route('monitoring.index') }}" class="nav-link {{ request()->routeIs('monitoring.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">◉</span> {{ __('panel.nav.monitoring') }}
            @if(!empty($realtimeEnabled))
                <span class="badge bg-primary ms-1" style="font-size:0.6rem;">WS</span>
            @endif
        </a>
        @endcan
        @can('ai.view')
        <a href="{{ route('ai.index') }}" class="nav-link {{ request()->routeIs('ai.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">🤖</span> {{ __('panel.nav.ai') }}
        </a>
        @endcan
        @can('websites.view')
        <a href="{{ route('websites.index') }}" class="nav-link {{ request()->routeIs('websites.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">◎</span> {{ __('panel.nav.websites') }}
        </a>
        @endcan
        @can('databases.view')
        <a href="{{ route('databases.index') }}" class="nav-link {{ request()->routeIs('databases.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">▤</span> {{ __('panel.nav.databases') }}
        </a>
        @endcan
        @can('docker.view')
        <a href="{{ route('docker.index') }}" class="nav-link {{ request()->routeIs('docker.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">⬡</span> {{ __('panel.nav.docker') }}
        </a>
        @endcan
        @can('backups.view')
        <a href="{{ route('backups.index') }}" class="nav-link {{ request()->routeIs('backups.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">⬚</span> {{ __('panel.nav.backups') }}
        </a>
        @endcan
        @can('modules.view')
        @foreach(\App\Support\InfrastructureModuleRegistry::diagnostics() as $slug => $diag)
        @if(\Illuminate\Support\Facades\Route::has($diag['route']))
        <a href="{{ route($diag['route']) }}" class="nav-link {{ \App\Support\InfrastructureModuleRegistry::isDiagnosticRouteActive($slug) ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">{{ $diag['icon'] }}</span> {{ $diag['name'] }}
        </a>
        @endif
        @endforeach
        @endcan
        @if(auth()->user()?->can('modules.view') || auth()->user()?->can('users.view'))
        @if(!empty($infraModules) || !empty($stubModules))
        <hr class="border-secondary opacity-25 my-2">
        <button type="button"
                id="infra-toggle"
                class="nav-link w-100 text-start border-0 bg-transparent d-flex align-items-center justify-content-between py-2 px-2 small obiora-infra-toggle"
                aria-expanded="false"
                aria-controls="infra-nav"
                data-force-open="{{ request()->routeIs('modules.stub') || request()->routeIs('ssl.*', 'firewall.*', 'users.*', 'nginx.*', 'redis.*', 'apache.*', 'ftp.*', 'dns.*', 'applications.*', 'virtualizor.*', 'cluster.*') ? '1' : '0' }}">
            <span>{{ __('panel.nav.infrastructure') }}</span>
            <span class="infra-chevron opacity-75" aria-hidden="true">▸</span>
        </button>
        <div class="collapse {{ request()->routeIs('modules.stub') || request()->routeIs('ssl.*', 'firewall.*', 'users.*', 'nginx.*', 'redis.*', 'apache.*', 'ftp.*', 'dns.*', 'applications.*', 'virtualizor.*', 'cluster.*') ? 'show' : '' }}" id="infra-nav">
            @foreach($infraModules ?? [] as $slug => $module)
            @if(\Illuminate\Support\Facades\Route::has($module['route']))
            @if($slug === 'users' ? auth()->user()?->can('users.view') : auth()->user()?->can('modules.view'))
            <a href="{{ route($module['route']) }}" class="nav-link small py-1 ps-4 {{ request()->routeIs($module['route']) ? 'active' : '' }}">
                <span class="me-1">{{ $module['icon'] ?? '◫' }}</span>{{ $module['name'] ?? $slug }}
            </a>
            @endif
            @endif
            @endforeach
            @can('modules.view')
            @foreach($stubModules as $slug => $stub)
            <a href="{{ route('modules.stub', $slug) }}" class="nav-link small py-1 ps-4 {{ request()->routeIs('modules.stub') && request()->route('slug') === $slug ? 'active' : '' }}">
                <span class="me-1">{{ $stub['icon'] ?? '◫' }}</span>{{ $stub['name'] ?? $slug }}
            </a>
            @endforeach
            @endcan
        </div>
        @endif
        @endif
        @can('updates.view')
        <hr class="border-secondary opacity-25 my-2">
        <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <span class="obiora-nav-icon" aria-hidden="true">◈</span> {{ __('panel.nav.license') }}
            @if(!empty($updateAvailable))
                <span class="badge bg-warning text-dark ms-1">!</span>
            @endif
        </a>
        @endcan
    </nav>

    <div class="mt-auto pt-4 small text-muted">
        v{{ $panelVersion ?? config('obiora.version') }}
    </div>
</aside>
