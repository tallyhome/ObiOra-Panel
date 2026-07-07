<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Marketplace</h1>
            <p class="text-muted mb-0">Applications en un clic — serveur : <strong>{{ $serverName }}</strong></p>
        </div>
    </div>

    @if ($installed->isNotEmpty())
        <div class="card obiora-card mb-4">
            <div class="card-header py-2 fw-medium">Installées sur ce serveur</div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    @foreach ($installed as $app)
                        <span class="badge text-bg-success d-inline-flex align-items-center gap-2 py-2 px-3">
                            {{ $app->name }}
                            <button type="button" wire:loading.attr="disabled"
                                onclick="obioraConfirm(() => $wire.uninstall({{ $app->id }}), 'Désinstaller', @js('Désinstaller '.$app->name.' ?'))"
                                class="btn-close btn-close-white btn-sm" style="font-size: .6rem;"></button>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <input wire:model.live.debounce.300ms="search" type="search" class="form-control form-control-sm" placeholder="Rechercher une app...">
        </div>
        <div class="col-md-3">
            <select wire:model.live="category" class="form-select form-select-sm">
                <option value="">Toutes les catégories</option>
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="row g-3">
        @forelse ($filtered as $package)
            @php $isInstalled = in_array($package->slug, $installedSlugs, true); @endphp
            <div class="col-md-6 col-lg-4">
                <div class="card obiora-card h-100 {{ $isInstalled ? 'border-success' : '' }}">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h2 class="h6 mb-0">{{ $package->name() }}</h2>
                            <span class="badge text-bg-light text-dark">{{ $categories[$package->category()] ?? $package->category() }}</span>
                        </div>
                        <p class="small text-muted flex-grow-1">{{ $package->description() }}</p>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <span class="small text-muted">v{{ $package->version() }}</span>
                            @if ($isInstalled)
                                <span class="badge text-bg-success">Installé</span>
                            @else
                                <button
                                    wire:click="install('{{ $package->slug }}')"
                                    class="btn btn-primary btn-sm"
                                    wire:loading.attr="disabled"
                                    wire:target="install('{{ $package->slug }}')"
                                >
                                    <span wire:loading.remove wire:target="install('{{ $package->slug }}')">Installer</span>
                                    <span wire:loading wire:target="install('{{ $package->slug }}')">Installation…</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-secondary mb-0">Aucune application dans le catalogue.</div>
            </div>
        @endforelse
    </div>

    <p class="small text-muted mt-4">
        Catalogue extensible via <code>packages/{slug}/manifest.json</code> + scripts shell (style Swizzin).
    </p>
</div>
