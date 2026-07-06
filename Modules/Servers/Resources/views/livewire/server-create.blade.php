<div>
    <div class="mb-4">
        <a href="{{ route('servers.index') }}" class="text-decoration-none small">&larr; Retour aux serveurs</a>
        <h1 class="h3 mt-2">Ajouter un serveur distant</h1>
        <p class="text-muted">Le serveur distant doit avoir l'agent ObiOra installé et accessible.</p>
    </div>

    <div class="card obiora-card">
        <div class="card-body p-4">
            <form wire:submit="save">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nom</label>
                        <input wire:model="name" type="text" class="form-control @error('name') is-invalid @enderror" placeholder="VPS Production">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Adresse IP</label>
                        <input wire:model="ip_address" type="text" class="form-control @error('ip_address') is-invalid @enderror" placeholder="203.0.113.10">
                        @error('ip_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hostname (optionnel)</label>
                        <input wire:model="hostname" type="text" class="form-control" placeholder="vps1.example.com">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select wire:model="type" class="form-select">
                            <option value="vps">VPS</option>
                            <option value="dedicated">Dédié</option>
                            <option value="cluster">Cluster</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Port agent</label>
                        <input wire:model="agent_port" type="number" class="form-control">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Ajouter le serveur</button>
                </div>
            </form>
        </div>
    </div>
</div>
