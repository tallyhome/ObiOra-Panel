<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Sites web</h1>
            <p class="text-muted mb-0">Serveur : <strong>{{ $serverName }}</strong></p>
        </div>
        <a href="{{ route('websites.create') }}" class="btn btn-primary btn-sm">Créer un site</a>
    </div>

    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Domaine</th>
                        <th>PHP</th>
                        <th>SSL</th>
                        <th>Statut</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($websites as $website)
                        <tr>
                            <td>
                                <a href="{{ route('websites.show', $website) }}" class="text-decoration-none fw-medium">
                                    {{ $website->domain }}
                                </a>
                                <div class="small text-muted font-monospace">{{ $website->document_root }}</div>
                            </td>
                            <td><span class="badge text-bg-light text-dark">{{ $website->php_version }}</span></td>
                            <td>
                                @if ($website->ssl_enabled)
                                    <span class="badge text-bg-success">HTTPS</span>
                                    @if ($website->ssl_expires_at)
                                        <div class="small text-muted">expire {{ $website->ssl_expires_at->diffForHumans() }}</div>
                                    @endif
                                @else
                                    <span class="badge text-bg-secondary">HTTP</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $badge = match($website->status->value) {
                                        'active' => 'success',
                                        'error' => 'danger',
                                        'pending' => 'warning',
                                        default => 'secondary',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $badge }}">{{ $website->status->value }}</span>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('websites.show', $website) }}" class="btn btn-outline-primary btn-sm">Gérer</a>
                                <button type="button" wire:loading.attr="disabled"
                                    onclick="obioraConfirmWire(this, 'delete', 'Supprimer le site', 'Supprimer ce site et sa configuration Nginx ?', {{ $website->id }})"
                                    class="btn btn-outline-danger btn-sm">Supprimer</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                Aucun site web sur ce serveur.
                                <a href="{{ route('websites.create') }}">Créer le premier</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
