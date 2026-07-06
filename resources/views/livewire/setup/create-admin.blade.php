<div class="card obiora-card shadow-sm">
    <div class="card-body p-4">
        <h2 class="h5 mb-2">Configuration initiale</h2>
        <p class="text-muted small mb-4">Créez le compte administrateur principal.</p>

        <form wire:submit="save">
            <div class="mb-3">
                <label class="form-label" for="name">Nom</label>
                <input wire:model="name" type="text" id="name" class="form-control @error('name') is-invalid @enderror">
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input wire:model="email" type="email" id="email" class="form-control @error('email') is-invalid @enderror">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Mot de passe</label>
                <input wire:model="password" type="password" id="password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password_confirmation">Confirmer</label>
                <input wire:model="password_confirmation" type="password" id="password_confirmation" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">
                <span wire:loading.remove>Créer le compte admin</span>
                <span wire:loading>Création...</span>
            </button>
        </form>
    </div>
</div>
