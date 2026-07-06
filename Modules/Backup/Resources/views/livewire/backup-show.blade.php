<div>
    <div class="mb-4">
        <a href="{{ route('backups.index') }}" class="text-decoration-none small">&larr; Retour</a>
        <h1 class="h3 mt-2 mb-0">{{ $backup->name }}</h1>
        <p class="text-muted mb-0">Serveur : {{ $backup->server->name }}</p>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card obiora-card">
                <div class="card-header py-2 fw-medium">Détails</div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4">Type</dt>
                        <dd class="col-sm-8"><span class="badge text-bg-light text-dark">{{ $backup->type->value }}</span></dd>

                        <dt class="col-sm-4">Fichier</dt>
                        <dd class="col-sm-8 font-monospace">{{ $backup->filename ?: '—' }}</dd>

                        <dt class="col-sm-4">Chemin</dt>
                        <dd class="col-sm-8 font-monospace small">{{ $backup->storage_path ?: '—' }}</dd>

                        <dt class="col-sm-4">Taille</dt>
                        <dd class="col-sm-8">{{ $backup->humanSize() }}</dd>

                        <dt class="col-sm-4">Cible</dt>
                        <dd class="col-sm-8">{{ $backup->target ?? '—' }}</dd>

                        <dt class="col-sm-4">Statut</dt>
                        <dd class="col-sm-8">
                            @php
                                $badge = match($backup->status->value) {
                                    'completed' => 'success',
                                    'error' => 'danger',
                                    default => 'warning',
                                };
                            @endphp
                            <span class="badge text-bg-{{ $badge }}">{{ $backup->status->value }}</span>
                        </dd>

                        <dt class="col-sm-4">Terminée</dt>
                        <dd class="col-sm-8">{{ $backup->completed_at?->format('d/m/Y H:i:s') ?? '—' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @if ($backup->type->value === 'database' && $backup->status->value === 'completed')
                <div class="card obiora-card mb-3">
                    <div class="card-header py-2 fw-medium">Restauration SQL</div>
                    <div class="card-body">
                        <form wire:submit="restore">
                            <div class="mb-3">
                                <label for="restore_database" class="form-label">Base de destination</label>
                                <input wire:model="restore_database" type="text" id="restore_database" class="form-control font-monospace" placeholder="nom_base">
                            </div>
                            <button type="submit" class="btn btn-warning btn-sm" wire:confirm="Restaurer cette sauvegarde ? Les données existantes seront écrasées." wire:loading.attr="disabled">
                                Restaurer
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card obiora-card border-danger">
                <div class="card-body">
                    <h2 class="h6 text-danger">Zone dangereuse</h2>
                    <p class="small text-muted mb-2">Supprime l'archive et l'entrée en base.</p>
                    <button wire:click="delete" wire:confirm="Supprimer définitivement cette sauvegarde ?" class="btn btn-outline-danger btn-sm">Supprimer</button>
                </div>
            </div>
        </div>
    </div>
</div>
