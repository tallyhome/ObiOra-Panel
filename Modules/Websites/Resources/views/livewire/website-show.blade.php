<div>
    <div class="mb-4">
        <a href="{{ route('websites.index') }}" class="text-decoration-none small">&larr; Retour aux sites</a>
        <h1 class="h3 mt-2 mb-0">{{ $website->domain }}</h1>
        <p class="text-muted mb-0">Serveur : {{ $website->server->name }}</p>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card obiora-card">
                <div class="card-header py-2 fw-medium">Configuration</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4">Document root</dt>
                        <dd class="col-sm-8 font-monospace">{{ $website->document_root ?: '—' }}</dd>

                        <dt class="col-sm-4">PHP</dt>
                        <dd class="col-sm-8">{{ $website->php_version }}</dd>

                        <dt class="col-sm-4">Nginx</dt>
                        <dd class="col-sm-8 font-monospace">{{ $website->nginx_config_path ?: '—' }}</dd>

                        <dt class="col-sm-4">Statut</dt>
                        <dd class="col-sm-8">
                            @php
                                $badge = match($website->status->value) {
                                    'active' => 'success',
                                    'error' => 'danger',
                                    'pending' => 'warning',
                                    default => 'secondary',
                                };
                            @endphp
                            <span class="badge text-bg-{{ $badge }}">{{ $website->status->value }}</span>
                        </dd>

                        <dt class="col-sm-4">SSL</dt>
                        <dd class="col-sm-8">
                            @if ($website->ssl_enabled)
                                <span class="badge text-bg-success">Actif</span>
                                @if ($website->ssl_expires_at)
                                    <span class="text-muted">— expire {{ $website->ssl_expires_at->format('d/m/Y') }}</span>
                                @endif
                            @else
                                <span class="badge text-bg-secondary">Non configuré</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @unless ($website->ssl_enabled)
                <div class="card obiora-card mb-3">
                    <div class="card-header py-2 fw-medium">SSL Let's Encrypt</div>
                    <div class="card-body">
                        <form wire:submit="enableSsl">
                            <div class="mb-3">
                                <label for="ssl_email" class="form-label">Email</label>
                                <input wire:model="ssl_email" type="email" id="ssl_email" class="form-control @error('ssl_email') is-invalid @enderror" required>
                                @error('ssl_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <button type="submit" class="btn btn-success btn-sm" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="enableSsl">Activer HTTPS</span>
                                <span wire:loading wire:target="enableSsl">Certbot en cours…</span>
                            </button>
                        </form>
                    </div>
                </div>
            @endunless

            <div class="card obiora-card border-danger">
                <div class="card-body">
                    <h2 class="h6 text-danger">Zone dangereuse</h2>
                    <p class="small text-muted mb-2">Supprime le vhost Nginx, les fichiers et l'entrée en base.</p>
                    <button type="button" wire:loading.attr="disabled"
                        onclick="obioraConfirm(() => $wire.delete(), 'Supprimer le site', 'Supprimer définitivement ce site ?')"
                        class="btn btn-outline-danger btn-sm">Supprimer le site</button>
                </div>
            </div>
        </div>
    </div>
</div>
