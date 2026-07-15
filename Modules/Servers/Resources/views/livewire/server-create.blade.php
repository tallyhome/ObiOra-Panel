<div>
    <div class="mb-4">
        <a href="{{ route('servers.index') }}" class="text-decoration-none small">&larr; Retour aux serveurs</a>
        <h1 class="h3 mt-2">Ajouter un serveur distant</h1>
    </div>

    <div class="alert alert-info small">
        <strong>Recommandé :</strong> enregistrez le serveur ici (IP + nom), puis sur la fiche serveur utilisez
        <strong>Installation automatique (SSH)</strong> pour installer l'agent seedbox — comme Doctor &amp; Suite.<br>
        <span class="text-muted">Alternative manuelle :</span>
        <code class="user-select-all">curl -fsSL {{ url('/install/slave-agent.sh') }} | sudo OBIORA_AGENT_TOKEN=… bash</code>
    </div>

    <div class="card obiora-card">
        <div class="card-body p-4">
            <form wire:submit="save">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nom</label>
                        <input wire:model="name" type="text" class="form-control obiora-input @error('name') is-invalid @enderror" placeholder="VPS Production">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Adresse IP</label>
                        <input wire:model="ip_address" type="text" class="form-control obiora-input @error('ip_address') is-invalid @enderror" placeholder="203.0.113.10">
                        @error('ip_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hostname (optionnel)</label>
                        <input wire:model="hostname" type="text" class="form-control obiora-input" placeholder="vps1.example.com">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select wire:model.live="type" class="form-select">
                            <option value="vps">VPS</option>
                            <option value="dedicated">Dédié</option>
                            <option value="cluster">Cluster</option>
                        </select>
                    </div>
                    @if($type === 'dedicated')
                    <div class="col-md-3">
                        <label class="form-label">Profil hôte</label>
                        <select wire:model="host_profile" class="form-select">
                            @foreach(\App\Enums\DedicatedHostProfile::selectable() as $profile)
                                <option value="{{ $profile->value }}">{{ $profile->label() }}</option>
                            @endforeach
                        </select>
                        <p class="form-text small mb-0">Virtualizor, Proxmox, bare metal OVH… — adapte Doctor et l'install panel.</p>
                    </div>
                    @endif
                    <div class="col-md-3">
                        <label class="form-label">Port agent</label>
                        <input wire:model="agent_port" type="number" class="form-control obiora-input">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Clé API agent (optionnel)</label>
                        <input wire:model="agent_token" type="text" class="form-control obiora-input font-monospace @error('agent_token') is-invalid @enderror" placeholder="Laisser vide = génération auto (recommandé pour install SSH)">
                        @error('agent_token') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <p class="form-text small mb-0">Laissez vide : le panel génère le token utilisé lors de l'installation SSH.</p>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove>Enregistrer le serveur</span>
                        <span wire:loading>Enregistrement…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
