<header class="obiora-navbar px-4 py-3 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
        <livewire:server-switcher />
    </div>

    <div class="d-flex align-items-center gap-3">
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-2"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false">
                <span class="obiora-user-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-3 py-2 small text-muted border-bottom border-secondary">
                    {{ auth()->user()->email }}
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('profile.index') }}">Mon profil</a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}" class="px-0">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">Déconnexion</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
