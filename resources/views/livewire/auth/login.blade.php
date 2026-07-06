<div class="card obiora-card shadow-sm">
    <div class="card-body p-4">
        <h2 class="h5 mb-4">Connexion</h2>

        <form wire:submit="login">
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input wire:model="email" type="email" id="email" class="form-control @error('email') is-invalid @enderror" autofocus>
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Mot de passe</label>
                <input wire:model="password" type="password" id="password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="mb-3 form-check">
                <input wire:model="remember" type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Se souvenir de moi</label>
            </div>

            <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">
                <span wire:loading.remove>Se connecter</span>
                <span wire:loading>Connexion...</span>
            </button>
        </form>
    </div>
</div>
