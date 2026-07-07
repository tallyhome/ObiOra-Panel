<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Serveurs</h1>
        <a href="{{ route('servers.create') }}" class="btn btn-primary btn-sm">Ajouter un serveur</a>
    </div>

    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>IP</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Dernière vue</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($servers as $server)
                        <tr>
                            <td>
                                <a href="{{ route('servers.show', $server) }}" class="text-decoration-none fw-medium">
                                    {{ $server->name }}
                                </a>
                                @if ($server->is_master)
                                    <span class="badge text-bg-primary ms-1">maître</span>
                                @endif
                            </td>
                            <td>{{ $server->ip_address }}</td>
                            <td><span class="badge text-bg-light text-dark">{{ $server->type->value }}</span></td>
                            <td>
                                @php
                                    $badge = match($server->status->value) {
                                        'online' => 'success',
                                        'offline', 'error' => 'danger',
                                        'pending' => 'warning',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $badge }}">{{ $server->status->value }}</span>
                            </td>
                            <td class="text-muted small">{{ $server->last_seen_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-end">
                                <button wire:click="ping({{ $server->id }})" class="btn btn-outline-secondary btn-sm">Ping</button>
                                @unless ($server->is_master)
                                    <button type="button" wire:loading.attr="disabled"
                                        onclick="obioraConfirm(() => $wire.delete({{ $server->id }}), 'Supprimer le serveur', 'Supprimer ce serveur du panel ?')"
                                        class="btn btn-outline-danger btn-sm">Supprimer</button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Aucun serveur configuré.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
