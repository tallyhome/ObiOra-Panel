<div>
    <div class="mb-4">
        <a href="{{ route('databases.index') }}" class="text-decoration-none small">&larr; Retour</a>
        <h1 class="h3 mt-2 mb-0">Créer une base de données</h1>
        <p class="text-muted">MySQL/MariaDB — base + utilisateur dédié avec privilèges.</p>
    </div>

    <div class="card obiora-card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="mb-3">
                    <label for="name" class="form-label">Nom de la base</label>
                    <input wire:model="name" type="text" id="name" class="form-control font-monospace @error('name') is-invalid @enderror" placeholder="mon_app" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Lettres, chiffres et underscore uniquement.</div>
                </div>

                <div class="mb-3">
                    <label for="username" class="form-label">Utilisateur MySQL <span class="text-muted">(optionnel)</span></label>
                    <input wire:model="username" type="text" id="username" class="form-control font-monospace" placeholder="auto : {nom}_user">
                </div>

                <div class="mb-3 form-check">
                    <input wire:model.live="auto_password" type="checkbox" class="form-check-input" id="auto_password">
                    <label class="form-check-label" for="auto_password">Générer un mot de passe automatiquement</label>
                </div>

                @unless ($auto_password)
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <input wire:model="password" type="text" id="password" class="form-control font-monospace @error('password') is-invalid @enderror" minlength="12">
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endunless

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Créer</span>
                    <span wire:loading wire:target="save">Création en cours…</span>
                </button>
            </form>
        </div>
    </div>
</div>
