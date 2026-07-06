<div>
    <div class="mb-4">
        <a href="{{ route('backups.index') }}" class="text-decoration-none small">&larr; Retour</a>
        <h1 class="h3 mt-2 mb-0">Nouvelle sauvegarde</h1>
        <p class="text-muted">Stockage : <code>{{ config('obiora.backups.storage_root') }}</code></p>
    </div>

    <div class="card obiora-card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="mb-3">
                    <label for="name" class="form-label">Nom</label>
                    <input wire:model="name" type="text" id="name" class="form-control @error('name') is-invalid @enderror" placeholder="backup-quotidien" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="type" class="form-label">Type</label>
                    <select wire:model.live="type" id="type" class="form-select">
                        <option value="database">Base de données (mysqldump)</option>
                        <option value="files">Fichiers (tar.gz)</option>
                        <option value="full">Complète (BDD + fichiers)</option>
                    </select>
                </div>

                @if ($type === 'database')
                    <div class="mb-3">
                        <label for="target" class="form-label">Base cible</label>
                        <input wire:model="target" type="text" id="target" class="form-control font-monospace" placeholder="all (toutes les bases utilisateur)">
                        <div class="form-text">Laissez vide ou « all » pour toutes les bases.</div>
                    </div>
                @elseif ($type === 'files')
                    <div class="mb-3">
                        <label for="target" class="form-label">Chemin</label>
                        <input wire:model="target" type="text" id="target" class="form-control font-monospace" placeholder="/var/www">
                        <div class="form-text">Par défaut : /var/www</div>
                    </div>
                @endif

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Lancer la sauvegarde</span>
                    <span wire:loading wire:target="save">Sauvegarde en cours…</span>
                </button>
            </form>
        </div>
    </div>
</div>
