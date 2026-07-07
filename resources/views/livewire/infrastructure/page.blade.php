<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $title }}</h1>
            <p class="text-muted mb-0">Module Infrastructure — serveur local</p>
        </div>
        <button type="button" wire:click="refresh" class="btn btn-outline-secondary btn-sm" wire:loading.attr="disabled">
            Actualiser
        </button>
    </div>

    @if($error)
        <div class="alert alert-warning">{{ $error }}</div>
    @endif

    @if($slug === 'firewall')
        <div class="card obiora-card mb-4">
            <div class="card-body">
                <h2 class="h6">Gestion port</h2>
                <div class="row g-2 align-items-end">
                    <div class="col-auto">
                        <select wire:model="portAction" class="form-select form-select-sm">
                            <option value="open">Ouvrir</option>
                            <option value="close">Fermer</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <input wire:model="portNumber" type="number" min="1" max="65535" class="form-control form-control-sm" style="width:120px">
                    </div>
                    @can('modules.manage')
                    <div class="col-auto">
                        <button type="button" wire:click="applyPort" class="btn btn-primary btn-sm">Appliquer</button>
                    </div>
                    @endcan
                </div>
            </div>
        </div>
    @endif

    <div class="card obiora-card">
        <div class="card-body">
            <pre class="small mb-0 text-light" style="white-space: pre-wrap;">{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </div>
</div>
