<header class="obiora-navbar px-4 py-3 d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
        <livewire:server-switcher />
    </div>

    <div class="d-flex align-items-center gap-3">
        <span class="text-muted small">{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Déconnexion</button>
        </form>
    </div>
</header>
