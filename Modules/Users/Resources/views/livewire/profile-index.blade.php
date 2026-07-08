<div>
    <div class="mb-4">
        <h1 class="h3 mb-1">Mon profil</h1>
        <p class="text-muted mb-0">Modifiez vos informations de connexion au panel.</p>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card obiora-card">
                <div class="card-body">
                    <form wire:submit="save" class="vstack gap-3">
                        <div>
                            <label class="form-label" for="profile-name">Nom</label>
                            <input wire:model="name" type="text" id="profile-name" class="form-control obiora-input" autocomplete="name">
                            @error('name') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="form-label" for="profile-email">Email</label>
                            <input wire:model="email" type="email" id="profile-email" class="form-control obiora-input" autocomplete="email">
                            @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <hr class="border-secondary opacity-25 my-1">

                        <p class="small text-muted mb-0">Laissez vide pour conserver le mot de passe actuel.</p>

                        <div>
                            <label class="form-label" for="profile-password">Nouveau mot de passe</label>
                            <input wire:model="password" type="password" id="profile-password" class="form-control obiora-input" autocomplete="new-password">
                            @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>

                        <div>
                            <label class="form-label" for="profile-password-confirm">Confirmer le mot de passe</label>
                            <input wire:model="password_confirmation" type="password" id="profile-password-confirm" class="form-control obiora-input" autocomplete="new-password">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">Informations du compte</h2>
                    <dl class="row small mb-0">
                        <dt class="col-sm-5">Rôle</dt>
                        <dd class="col-sm-7"><span class="badge bg-secondary">{{ $roleLabel }}</span></dd>
                        @if($lastLogin)
                            <dt class="col-sm-5">Dernière connexion</dt>
                            <dd class="col-sm-7">{{ $lastLogin }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
