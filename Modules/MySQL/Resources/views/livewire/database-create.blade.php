<div>
    <div class="mb-4">
        <a href="{{ route('databases.index') }}" class="text-decoration-none small">&larr; Retour</a>
        <h1 class="h3 mt-2 mb-0">Créer une base de données</h1>
        <p class="text-muted mb-0">Assistant type cPanel — base MySQL/MariaDB + utilisateur dédié.</p>
    </div>

    {{-- Indicateur d'étapes --}}
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        @foreach ([1 => 'Base de données', 2 => 'Utilisateur', 3 => 'Confirmation'] as $n => $label)
            @php
                $active = $step === $n;
                $done = $step > $n;
            @endphp
            <button
                type="button"
                @if ($done) wire:click="goToStep({{ $n }})" @endif
                class="btn btn-sm {{ $active ? 'btn-primary' : ($done ? 'btn-outline-success' : 'btn-outline-secondary') }}"
                @disabled(! $done && ! $active)
            >
                <span class="badge text-bg-{{ $active ? 'light text-primary' : 'secondary' }} me-1">{{ $n }}</span>
                {{ $label }}
            </button>
            @if ($n < 3)
                <span class="text-muted d-none d-sm-inline">›</span>
            @endif
        @endforeach
    </div>

    <div class="card obiora-card">
        <div class="card-body">
            {{-- Étape 1 : nom de la base --}}
            @if ($step === 1)
                <h2 class="h5 mb-3">Nouvelle base de données</h2>
                <p class="text-muted small">Choisissez un nom unique. Lettres, chiffres et underscore uniquement.</p>

                <div class="mb-3">
                    <label for="name" class="form-label">Nom de la base</label>
                    <input wire:model.live="name" type="text" id="name"
                        class="form-control font-monospace @error('name') is-invalid @enderror"
                        placeholder="mon_app" maxlength="64" autofocus>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4 form-check">
                    <input wire:model.live="create_user" type="checkbox" class="form-check-input" id="create_user">
                    <label class="form-check-label" for="create_user">
                        Créer un utilisateur et lui attribuer cette base
                    </label>
                    <div class="form-text">Recommandé — comme « Create Database and User » dans cPanel.</div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-primary" wire:click="nextStep">
                        Suivant
                    </button>
                </div>
            @endif

            {{-- Étape 2 : utilisateur --}}
            @if ($step === 2)
                <h2 class="h5 mb-3">Utilisateur MySQL</h2>

                @if ($create_user)
                    <div class="mb-3">
                        <label for="username" class="form-label">Nom d'utilisateur</label>
                        <input wire:model="username" type="text" id="username"
                            class="form-control font-monospace @error('username') is-invalid @enderror"
                            maxlength="32" placeholder="{{ $name }}_user">
                        @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Max. 32 caractères. Privilèges : ALL sur la base <code>{{ $name }}</code>.</div>
                    </div>

                    <div class="mb-3 form-check">
                        <input wire:model.live="auto_password" type="checkbox" class="form-check-input" id="auto_password">
                        <label class="form-check-label" for="auto_password">Générer un mot de passe fort automatiquement</label>
                    </div>

                    @unless ($auto_password)
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input wire:model="password" type="text" id="password"
                                class="form-control font-monospace @error('password') is-invalid @enderror"
                                minlength="12" maxlength="64">
                            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Minimum 12 caractères.</div>
                        </div>
                    @endunless
                @else
                    <div class="alert alert-info mb-0">
                        Aucun utilisateur ne sera créé. Vous pourrez gérer les accès plus tard via phpMyAdmin.
                    </div>
                @endif

                <div class="d-flex justify-content-between mt-4">
                    <button type="button" class="btn btn-outline-secondary" wire:click="previousStep">Retour</button>
                    <button type="button" class="btn btn-primary" wire:click="nextStep">Suivant</button>
                </div>
            @endif

            {{-- Étape 3 : récap --}}
            @if ($step === 3)
                <h2 class="h5 mb-3">Confirmation</h2>
                <p class="text-muted small">Vérifiez les informations avant de créer la base.</p>

                <dl class="row small mb-4">
                    <dt class="col-sm-4">Base de données</dt>
                    <dd class="col-sm-8 font-monospace">{{ $name }}</dd>

                    <dt class="col-sm-4">Utilisateur</dt>
                    <dd class="col-sm-8 font-monospace">{{ $previewUsername }}</dd>

                    <dt class="col-sm-4">Mot de passe</dt>
                    <dd class="col-sm-8">
                        @if (! $create_user)
                            —
                        @elseif ($auto_password)
                            <span class="text-muted">Généré automatiquement (affiché après création)</span>
                        @else
                            <span class="font-monospace">••••••••</span>
                        @endif
                    </dd>

                    <dt class="col-sm-4">Privilèges</dt>
                    <dd class="col-sm-8">
                        @if ($create_user)
                            <code>ALL PRIVILEGES</code> sur <code>{{ $name }}.*</code>
                            <div class="form-text">Hôtes : localhost, 127.0.0.1, réseau Docker</div>
                        @else
                            Aucun (base seule)
                        @endif
                    </dd>

                    <dt class="col-sm-4">Charset</dt>
                    <dd class="col-sm-8">utf8mb4 / utf8mb4_unicode_ci</dd>
                </dl>

                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" wire:click="previousStep" wire:loading.attr="disabled">
                        Retour
                    </button>
                    <button type="button" class="btn btn-success" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Créer la base de données</span>
                        <span wire:loading wire:target="save">Création en cours…</span>
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
