<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Maintenance</h1>
            <p class="text-muted small mb-0">Silences NOC — aucune alerte ni nouvel incident pendant la fenêtre.</p>
        </div>
        @if($canManage)
            <button type="button" wire:click="openModal" class="btn btn-primary btn-sm">+ Planifier</button>
        @endif
    </div>

    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-sm obiora-table align-middle mb-0">
                <thead class="obiora-table-head">
                    <tr>
                        <th>Portée</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Statut</th>
                        <th>Note</th>
                        @if($canManage)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($windows as $window)
                    <tr>
                        <td>
                            <span class="badge text-bg-secondary">{{ $window['resource_type'] }}</span>
                            @if(count($window['resource_ids']) > 0)
                                <span class="small text-muted">#{{ implode(', #', $window['resource_ids']) }}</span>
                            @endif
                        </td>
                        <td class="small">{{ $window['starts_at'] }}</td>
                        <td class="small">{{ $window['ends_at'] }}</td>
                        <td>
                            @if($window['active'])
                                <span class="badge text-bg-warning">En cours</span>
                            @elseif($window['scheduled'])
                                <span class="badge text-bg-info">Planifiée</span>
                            @else
                                <span class="badge text-bg-secondary">—</span>
                            @endif
                        </td>
                        <td class="small text-muted">{{ Str::limit($window['note'] ?? '—', 60) }}</td>
                        @if($canManage)
                        <td class="text-end">
                            <button type="button" wire:click="cancelWindow({{ $window['id'] }})" wire:confirm="Annuler cette fenêtre ?" class="btn btn-outline-danger btn-sm">Annuler</button>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $canManage ? 6 : 5 }}" class="text-center text-muted py-4">Aucune fenêtre planifiée.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($showModal)
    <div class="modal show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content obiora-card">
                <div class="modal-header">
                    <h2 class="modal-title h5">Planifier une maintenance</h2>
                    <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Portée</label>
                                <select wire:model.live="resourceType" class="form-select">
                                    <option value="all">Toute la flotte</option>
                                    <option value="server">Serveur(s)</option>
                                    <option value="monitor">Moniteur(s)</option>
                                </select>
                            </div>
                            @if($resourceType === 'server')
                            <div class="col-md-8">
                                <label class="form-label">Serveurs</label>
                                <select wire:model="resourceIds" class="form-select" multiple size="4">
                                    @foreach($servers as $server)
                                        <option value="{{ $server->id }}">{{ $server->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @elseif($resourceType === 'monitor')
                            <div class="col-md-8">
                                <label class="form-label">Moniteurs</label>
                                <select wire:model="resourceIds" class="form-select" multiple size="4">
                                    @foreach($monitors as $monitor)
                                        <option value="{{ $monitor->id }}">{{ $monitor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="col-md-6">
                                <label class="form-label">Début</label>
                                <input wire:model="startsAt" type="datetime-local" class="form-control obiora-input">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Fin</label>
                                <input wire:model="endsAt" type="datetime-local" class="form-control obiora-input">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Note (optionnel)</label>
                                <textarea wire:model="note" class="form-control obiora-input" rows="2" placeholder="Mise à jour kernel, migration DB…"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showModal', false)">Fermer</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <p class="small text-muted mt-3 mb-0">Fuseau affiché : {{ $timezoneFooter }}</p>
</div>
