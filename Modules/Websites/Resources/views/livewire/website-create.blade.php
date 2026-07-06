<div>
    <div class="mb-4">
        <a href="{{ route('websites.index') }}" class="text-decoration-none small">&larr; Retour aux sites</a>
        <h1 class="h3 mt-2 mb-0">Créer un site web</h1>
        <p class="text-muted">Provisionnement Nginx + PHP-FPM sur le serveur actif.</p>
    </div>

    <div class="card obiora-card">
        <div class="card-body">
            <form wire:submit="save">
                <div class="mb-3">
                    <label for="domain" class="form-label">Domaine</label>
                    <input wire:model="domain" type="text" id="domain" class="form-control @error('domain') is-invalid @enderror" placeholder="exemple.com" required>
                    @error('domain') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="php_version" class="form-label">Version PHP</label>
                    <select wire:model="php_version" id="php_version" class="form-select">
                        @foreach ($phpVersions as $version)
                            <option value="{{ $version }}">PHP {{ $version }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3 form-check">
                    <input wire:model.live="enable_ssl" type="checkbox" class="form-check-input" id="enable_ssl">
                    <label class="form-check-label" for="enable_ssl">Activer SSL Let's Encrypt</label>
                </div>

                @if ($enable_ssl)
                    <div class="mb-3">
                        <label for="ssl_email" class="form-label">Email Let's Encrypt</label>
                        <input wire:model="ssl_email" type="email" id="ssl_email" class="form-control @error('ssl_email') is-invalid @enderror" placeholder="admin@exemple.com">
                        @error('ssl_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endif

                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Créer le site</span>
                    <span wire:loading wire:target="save">Provisionnement en cours…</span>
                </button>
            </form>
        </div>
    </div>
</div>
