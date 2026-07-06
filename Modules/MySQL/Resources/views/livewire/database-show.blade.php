<div>
    <div class="mb-4">
        <a href="{{ route('databases.index') }}" class="text-decoration-none small">&larr; Retour</a>
        <h1 class="h3 mt-2 mb-0 font-monospace">{{ $database->name }}</h1>
        <p class="text-muted mb-0">Serveur : {{ $database->server->name }}</p>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card obiora-card">
                <div class="card-header py-2 fw-medium">Connexion</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4">Hôte</dt>
                        <dd class="col-sm-8 font-monospace">{{ $database->host }}</dd>

                        <dt class="col-sm-4">Base</dt>
                        <dd class="col-sm-8 font-monospace">{{ $database->name }}</dd>

                        <dt class="col-sm-4">Utilisateur</dt>
                        <dd class="col-sm-8 font-monospace">{{ $database->username }}</dd>

                        <dt class="col-sm-4">Mot de passe</dt>
                        <dd class="col-sm-8">
                            @if ($showPassword)
                                <code class="user-select-all">{{ $database->password_plain }}</code>
                            @else
                                <span class="text-muted">••••••••••••</span>
                            @endif
                            <button type="button" wire:click="togglePassword" class="btn btn-link btn-sm p-0 ms-2">
                                {{ $showPassword ? 'Masquer' : 'Afficher' }}
                            </button>
                        </dd>

                        <dt class="col-sm-4">Charset</dt>
                        <dd class="col-sm-8">{{ $database->charset }} / {{ $database->collation }}</dd>

                        <dt class="col-sm-4">Statut</dt>
                        <dd class="col-sm-8">
                            @php
                                $badge = match($database->status->value) {
                                    'active' => 'success',
                                    'error' => 'danger',
                                    'pending' => 'warning',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge text-bg-{{ $badge }}">{{ $database->status->value }}</span>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card obiora-card mt-3">
                <div class="card-header py-2 fw-medium">Chaîne DSN (Laravel .env)</div>
                <div class="card-body">
                    <pre class="small mb-0 bg-light p-3 rounded user-select-all">DB_CONNECTION=mysql
DB_HOST={{ $database->host }}
DB_PORT=3306
DB_DATABASE={{ $database->name }}
DB_USERNAME={{ $database->username }}
DB_PASSWORD={{ $showPassword ? $database->password_plain : '********' }}</pre>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card obiora-card border-danger">
                <div class="card-body">
                    <h2 class="h6 text-danger">Zone dangereuse</h2>
                    <p class="small text-muted mb-2">Supprime la base MySQL, l'utilisateur et l'entrée ObiOra.</p>
                    <button wire:click="delete" wire:confirm="Supprimer définitivement cette base ?" class="btn btn-outline-danger btn-sm">Supprimer</button>
                </div>
            </div>
        </div>
    </div>
</div>
