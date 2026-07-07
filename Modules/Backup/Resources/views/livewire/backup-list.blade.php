<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Sauvegardes</h1>
            <p class="text-muted mb-0">Serveur : <strong>{{ $serverName }}</strong></p>
        </div>
        <a href="{{ route('backups.create') }}" class="btn btn-primary btn-sm">Nouvelle sauvegarde</a>
    </div>

    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Taille</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($backups as $backup)
                        <tr>
                            <td>
                                <a href="{{ route('backups.show', $backup) }}" class="text-decoration-none fw-medium">
                                    {{ $backup->name }}
                                </a>
                                <div class="small text-muted font-monospace">{{ $backup->filename }}</div>
                            </td>
                            <td><span class="badge text-bg-light text-dark">{{ $backup->type->value }}</span></td>
                            <td>{{ $backup->humanSize() }}</td>
                            <td class="small text-muted">{{ $backup->completed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>
                                @php
                                    $badge = match($backup->status->value) {
                                        'completed' => 'success',
                                        'error' => 'danger',
                                        default => 'warning',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $badge }}">{{ $backup->status->value }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('backups.show', $backup) }}" class="btn btn-outline-primary btn-sm">Détails</a>
                                <button type="button" wire:loading.attr="disabled"
                                    onclick="obioraConfirm(() => $wire.delete({{ $backup->id }}), 'Supprimer la sauvegarde', 'Supprimer cette sauvegarde ?')"
                                    class="btn btn-outline-danger btn-sm">Supprimer</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Aucune sauvegarde.
                                <a href="{{ route('backups.create') }}">Créer la première</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
