<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Bases de données</h1>
            <p class="text-muted mb-0">Serveur : <strong>{{ $serverName }}</strong></p>
        </div>
        <div class="d-flex gap-2">
            <button wire:click="refresh" class="btn btn-outline-secondary btn-sm" wire:loading.attr="disabled">Actualiser</button>
            <a href="{{ route('databases.create') }}" class="btn btn-primary btn-sm">Créer une base</a>
        </div>
    </div>

    @if (count($serverDatabases))
        <div class="card obiora-card mb-3">
            <div class="card-body py-2 small text-muted">
                Bases détectées sur le serveur :
                @foreach ($serverDatabases as $db)
                    <span class="badge text-bg-light text-dark me-1">{{ $db }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Base</th>
                        <th>Utilisateur</th>
                        <th>Hôte</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($databases as $database)
                        <tr>
                            <td>
                                <a href="{{ route('databases.show', $database) }}" class="text-decoration-none fw-medium font-monospace">
                                    {{ $database->name }}
                                </a>
                            </td>
                            <td class="font-monospace small">{{ $database->username }}</td>
                            <td class="small text-muted">{{ $database->host }}</td>
                            <td>
                                @php
                                    $badge = match($database->status->value) {
                                        'active' => 'success',
                                        'error' => 'danger',
                                        'pending' => 'warning',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $badge }}">{{ $database->status->value }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('databases.show', $database) }}" class="btn btn-outline-primary btn-sm">Détails</a>
                                <button wire:click="delete({{ $database->id }})" wire:confirm="Supprimer cette base et l'utilisateur MySQL ?" class="btn btn-outline-danger btn-sm">Supprimer</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Aucune base gérée par ObiOra.
                                <a href="{{ route('databases.create') }}">Créer la première</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
