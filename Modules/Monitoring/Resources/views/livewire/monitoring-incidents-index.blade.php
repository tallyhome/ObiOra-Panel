<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="mb-4">
        <h1 class="h3 mb-1">Incidents</h1>
        <p class="text-muted small mb-0">Événements d'alerte, indisponibilités et journal des notifications.</p>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a href="{{ route('monitoring.incidents') }}" @class(['nav-link', 'active' => $viewTab === 'incidents'])>Incidents</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('monitoring.incidents.logs') }}" @class(['nav-link', 'active' => $viewTab === 'logs'])>Notification Logs</a>
        </li>
    </ul>

    @if($viewTab === 'incidents')
    <div class="d-flex flex-wrap gap-2 mb-3">
        <div class="btn-group btn-group-sm">
            <button type="button" wire:click="$set('statusFilter', 'all')" @class(['btn', 'btn-primary' => $statusFilter === 'all', 'btn-outline-secondary' => $statusFilter !== 'all'])>Tous</button>
            <button type="button" wire:click="$set('statusFilter', 'open')" @class(['btn', 'btn-primary' => $statusFilter === 'open', 'btn-outline-secondary' => $statusFilter !== 'open'])>Ouverts ({{ $openCount }})</button>
            <button type="button" wire:click="$set('statusFilter', 'resolved')" @class(['btn', 'btn-primary' => $statusFilter === 'resolved', 'btn-outline-secondary' => $statusFilter !== 'resolved'])>Résolus</button>
        </div>
        <div class="btn-group btn-group-sm">
            <button type="button" wire:click="$set('typeFilter', 'all')" @class(['btn', 'btn-outline-secondary', 'active' => $typeFilter === 'all'])>Tous types</button>
            <button type="button" wire:click="$set('typeFilter', 'servers')" @class(['btn', 'btn-outline-secondary', 'active' => $typeFilter === 'servers'])>Serveurs</button>
            <button type="button" wire:click="$set('typeFilter', 'monitors')" @class(['btn', 'btn-outline-secondary', 'active' => $typeFilter === 'monitors'])>Moniteurs</button>
        </div>
        <span class="small text-muted align-self-center ms-auto">{{ $totalCount }} incident(s)</span>
    </div>

    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-sm obiora-table align-middle mb-0">
                <thead class="obiora-table-head">
                    <tr>
                        <th>Ressource</th>
                        <th>Déclencheur</th>
                        <th>Message</th>
                        <th>Début</th>
                        <th>Récupéré</th>
                        <th>Durée</th>
                        <th>Statut</th>
                        @if($canManage)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($incidents as $incident)
                    <tr>
                        <td class="fw-medium">{{ $incident['resource'] }}</td>
                        <td><span class="text-warning">{{ $incident['trigger'] }}</span></td>
                        <td class="small">{{ $incident['message'] }}</td>
                        <td class="small text-nowrap text-danger">{{ $incident['went_down_at'] }}</td>
                        <td class="small">{{ $incident['recovered_at'] ?? '—' }}</td>
                        <td class="small">{{ $incident['duration'] }}</td>
                        <td>
                            @if($incident['status'] === 'open')
                                <span class="badge text-bg-danger">Open</span>
                            @else
                                <span class="badge text-bg-success">Resolved</span>
                            @endif
                        </td>
                        @if($canManage)
                        <td>
                            @if($incident['status'] === 'open')
                            <button type="button" wire:click="markResolved({{ $incident['id'] }})" class="btn btn-outline-success btn-sm py-0">Résoudre</button>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $canManage ? 8 : 7 }}" class="text-muted small">Aucun incident.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-sm obiora-table align-middle mb-0">
                <thead class="obiora-table-head">
                    <tr>
                        <th>Date</th>
                        <th>Contact</th>
                        <th>Canal</th>
                        <th>Incident</th>
                        <th>Statut</th>
                        <th>Réponse</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notificationLogs as $log)
                    <tr>
                        <td class="small text-nowrap">{{ $log['sent_at'] }}</td>
                        <td>{{ $log['contact'] }}</td>
                        <td><span class="badge text-bg-secondary">{{ $log['channel'] }}</span></td>
                        <td class="small">{{ $log['incident'] }}</td>
                        <td>
                            @if($log['status'] === 'sent')
                                <span class="badge text-bg-success">sent</span>
                            @else
                                <span class="badge text-bg-danger">failed</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ $log['response'] ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted small">Aucune notification envoyée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <p class="small text-muted mt-3 mb-0">Heures en {{ $timezoneFooter }} — {{ $nowLabel }}</p>
</div>
