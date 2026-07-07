<div>
    <div class="mb-4">
        <a href="{{ route('servers.index') }}" class="text-decoration-none small">&larr; Retour aux serveurs</a>
        <h1 class="h3 mt-2">Ajouter un serveur distant</h1>
    </div>

    <div class="alert alert-info small">
        <strong>1.</strong> Sur le serveur slave, exécutez :<br>
        <code class="user-select-all">bash &lt;(curl -fsSL https://raw.githubusercontent.com/tallyhome/ObiOra-Panel/main/Slave/install.sh)</code><br>
        <strong>2.</strong> Copiez la <strong>clé API</strong> affichée à la fin de l'installation.<br>
        <strong>3.</strong> Collez-la ci-dessous avec l'adresse IP du slave.
    </div>

    <div class="card obiora-card">
        <div class="card-body p-4">
            <form wire:submit="save">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nom</label>
                        <input wire:model="name" type="text" class="form-control obiora-input @error('name') is-invalid @enderror" placeholder="VPS Production" style="color:#e8e8f0;background-color:#1a1a26;">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Adresse IP</label>
                        <input wire:model="ip_address" type="text" class="form-control obiora-input @error('ip_address') is-invalid @enderror" placeholder="203.0.113.10" style="color:#e8e8f0;background-color:#1a1a26;">
                        @error('ip_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hostname (optionnel)</label>
                        <input wire:model="hostname" type="text" class="form-control obiora-input" placeholder="vps1.example.com" style="color:#e8e8f0;background-color:#1a1a26;">
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
                    <div class="col-12">
                        <label class="form-label">Clé API (générée sur le slave)</label>
                        <input wire:model="agent_token" type="text" class="form-control obiora-input font-monospace @error('agent_token') is-invalid @enderror" placeholder="Collez la clé affichée après Slave/install.sh" style="color:#e8e8f0;background-color:#1a1a26;">
                        @error('agent_token') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove>Lier le serveur</span>
                        <span wire:loading>Vérification...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
