<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Moniteurs</h1>
            <p class="text-muted small mb-0">Sites, API, ports et DNS surveillés depuis le panel.</p>
        </div>
        @if($canManage)
        <div class="d-flex gap-1">
            <button type="button" wire:click="openImportModal" class="btn btn-outline-secondary btn-sm">Import JSON</button>
            <a href="{{ route('monitoring.v1.monitors.export') }}" class="btn btn-outline-secondary btn-sm">Export JSON</a>
            <button type="button" wire:click="openAddModal" class="btn btn-primary btn-sm">+ Ajouter un moniteur</button>
        </div>
        @endif
    </div>

    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-sm obiora-table align-middle mb-0">
                <thead class="obiora-table-head">
                    <tr>
                        <th>Nom</th>
                        <th>Type</th>
                        <th>Cible</th>
                        <th>Statut</th>
                        <th>Réponse</th>
                        <th>Dernière vérif.</th>
                        @if($canManage)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($monitors as $monitor)
                    <tr @class(['opacity-50' => !$monitor['is_active']])>
                        <td>
                            <a href="{{ route('monitoring.monitors.show', $monitor['id']) }}" class="fw-medium">{{ $monitor['name'] }}</a>
                            @if(!$monitor['is_active'])
                                <span class="badge text-bg-secondary ms-1">Pause</span>
                            @endif
                        </td>
                        <td><span class="badge text-bg-secondary">{{ $monitor['type'] }}</span></td>
                        <td class="small text-break">{{ $monitor['target'] }}</td>
                        <td>
                            @if($monitor['status'] === 'up')
                                <span class="badge text-bg-success">Up</span>
                            @elseif($monitor['status'] === 'down')
                                <span class="badge text-bg-danger">Down</span>
                            @else
                                <span class="badge text-bg-secondary">Unknown</span>
                            @endif
                        </td>
                        <td class="small">{{ $monitor['response_ms'] ? $monitor['response_ms'].' ms' : '—' }}</td>
                        <td class="small text-nowrap">{{ $monitor['last_checked'] ?: '—' }}</td>
                        @if($canManage)
                        <td class="text-nowrap">
                            <button type="button" wire:click="editMonitor({{ $monitor['id'] }})" class="btn btn-outline-secondary btn-sm py-0">Modifier</button>
                            <button type="button" wire:click="toggleActive({{ $monitor['id'] }})" class="btn btn-outline-warning btn-sm py-0">
                                {{ $monitor['is_active'] ? 'Pause' : 'Reprendre' }}
                            </button>
                            <button type="button" wire:click="deleteMonitor({{ $monitor['id'] }})" wire:confirm="Supprimer ce moniteur ?"
                                    class="btn btn-outline-danger btn-sm py-0">Supprimer</button>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($monitors->isEmpty())
        <div class="card-body text-muted small">Aucun moniteur. Les sondes s'exécutent chaque minute selon l'intervalle choisi.</div>
        @endif
    </div>

    <p class="small text-muted mt-3 mb-0">Heures en {{ $timezoneFooter }} — {{ $nowLabel }}</p>

    @if($showAddModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content obiora-card">
                <div class="modal-header">
                    <h2 class="modal-title h5">{{ $editingId ? 'Modifier le moniteur' : 'Ajouter un moniteur' }}</h2>
                    <button type="button" class="btn-close" wire:click="$set('showAddModal', false)"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Nom *</label>
                            <input type="text" wire:model="name" class="form-control" placeholder="Ex. Site principal">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Type *</label>
                            <select wire:model.live="type" class="form-select">
                                @foreach($typeChoices as $choice)
                                    <option value="{{ $choice->value }}">{{ $choice->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Cible *</label>
                            <input type="text" wire:model="target" class="form-control"
                                   placeholder="{{ in_array($type, ['https','http','keyword']) ? 'https://example.com' : 'example.com' }}">
                        </div>
                        @if($type === 'port')
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Port *</label>
                            <input type="number" wire:model="port" class="form-control" min="1" max="65535" placeholder="443">
                        </div>
                        @endif
                        @if($type === 'keyword')
                        <div class="col-md-8">
                            <label class="form-label small fw-medium">Mot-clé *</label>
                            <input type="text" wire:model="keyword" class="form-control" placeholder="Texte attendu dans la page">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" wire:model="keywordPresent" class="form-check-input" id="kwPresent">
                                <label class="form-check-label small" for="kwPresent">Doit être présent</label>
                            </div>
                        </div>
                        @endif
                        @if($type === 'dns')
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Type d'enregistrement</label>
                            <select wire:model="dnsRecordType" class="form-select">
                                <option value="A">A</option>
                                <option value="AAAA">AAAA</option>
                                <option value="CNAME">CNAME</option>
                                <option value="MX">MX</option>
                                <option value="TXT">TXT</option>
                                <option value="NS">NS</option>
                            </select>
                        </div>
                        @endif
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Intervalle</label>
                            <select wire:model="intervalSeconds" class="form-select">
                                @foreach($intervalChoices as $choice)
                                    <option value="{{ $choice['value'] }}">{{ $choice['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Tags</label>
                            <input type="text" wire:model="tagsInput" class="form-control" placeholder="production, api">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="$set('showAddModal', false)">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="saveMonitor" wire:loading.attr="disabled">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($showImportModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content obiora-card">
                <div class="modal-header">
                    <h2 class="modal-title h5">Importer des moniteurs (JSON)</h2>
                    <button type="button" class="btn-close" wire:click="$set('showImportModal', false)"></button>
                </div>
                <div class="modal-body">
                    <textarea wire:model="importJson" class="form-control font-monospace small" rows="12" placeholder='{"version":1,"monitors":[...]}'></textarea>
                    @error('importJson')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="$set('showImportModal', false)">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="importMonitors">Importer</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
