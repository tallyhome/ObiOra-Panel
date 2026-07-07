<div>
    <div class="mb-4">
        <h1 class="h3 mb-1">Virtualizor</h1>
        <p class="text-muted mb-0">Configuration API pour provisioning VPS (Phase 13)</p>
    </div>

    <div class="card obiora-card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="mb-3">
                    <label class="form-label">URL API Virtualizor</label>
                    <input wire:model="apiUrl" type="url" class="form-control obiora-input" placeholder="https://vps.example.com:4085">
                </div>
                <div class="mb-3">
                    <label class="form-label">Clé API</label>
                    <input wire:model="apiKey" type="password" class="form-control obiora-input">
                </div>
                @can('modules.manage')
                    <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                @endcan
            </form>
            <p class="text-muted small mt-3 mb-0">Le provisioning automatique sera branché dans une release ultérieure.</p>
        </div>
    </div>
</div>
