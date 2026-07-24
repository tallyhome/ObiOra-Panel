<div>
    <div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <a href="{{ route('databases.index') }}" class="text-decoration-none small">&larr; Retour</a>
            <h1 class="h3 mt-2 mb-0 font-monospace">{{ $database->name }}</h1>
            <p class="text-muted mb-0">Serveur : {{ $database->server->name }}</p>
        </div>
        <button type="button" wire:click="openPhpMyAdmin" class="btn btn-outline-primary btn-sm" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="openPhpMyAdmin">Ouvrir phpMyAdmin</span>
            <span wire:loading wire:target="openPhpMyAdmin">Ouverture…</span>
        </button>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card obiora-card">
                <div class="card-header py-2 fw-medium">Connexion</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4">Hôte (applications sur le serveur)</dt>
                        <dd class="col-sm-8 font-monospace">{{ $database->host }}</dd>

                        <dt class="col-sm-4">Hôte (conteneurs Docker)</dt>
                        <dd class="col-sm-8">
                            <code class="user-select-all">{{ $database->metadata['docker_host'] ?? '172.17.0.1' }}</code>
                            <span class="text-muted small ms-1">ou <code>host.docker.internal</code></span>
                            <div class="form-text text-warning mt-1">
                                N'utilisez pas <code>localhost</code> depuis un conteneur Docker.
                                Les droits MySQL pour Docker sont appliqués ; si la connexion échoue encore,
                                contactez l'administrateur (écoute MariaDB sur le réseau Docker).
                            </div>
                            @if (empty($database->metadata['docker_granted_at']))
                                <button type="button" wire:click="grantDockerAccess" class="btn btn-outline-warning btn-sm mt-2" wire:loading.attr="disabled">
                                    Activer l'accès Docker pour cette base
                                </button>
                            @endif
                        </dd>

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
DB_PASSWORD={{ $showPassword ? $database->password_plain : '********' }}

# Applications Docker (Nextcloud, etc.)
DB_HOST={{ $database->metadata['docker_host'] ?? '172.17.0.1' }}</pre>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card obiora-card border-danger">
                <div class="card-body">
                    <h2 class="h6 text-danger">Zone dangereuse</h2>
                    <p class="small text-muted mb-2">Supprime la base MySQL, l'utilisateur et l'entrée ObiOra.</p>
                    <button type="button" wire:loading.attr="disabled"
                        onclick="obioraConfirmWire(this, 'delete', 'Supprimer la base', 'Supprimer définitivement cette base ?')"
                        class="btn btn-outline-danger btn-sm">Supprimer</button>
                </div>
            </div>
        </div>
    </div>
</div>
