<div>
    @include('monitoring::partials.monitoring-nav')

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">Alertes</h1>
            <p class="text-muted small mb-0">Politiques d'alerte et contacts de notification.</p>
        </div>
        @if($canManage)
            @if($activeTab === 'policies')
            <button type="button" wire:click="openPolicyModal" class="btn btn-primary btn-sm">+ Nouvelle politique</button>
            @else
            <button type="button" wire:click="openContactModal" class="btn btn-primary btn-sm">+ Nouveau contact</button>
            @endif
        @endif
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a href="{{ route('monitoring.alerts') }}" @class(['nav-link', 'active' => $activeTab === 'policies'])>Politiques</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('monitoring.alerts.contacts') }}" @class(['nav-link', 'active' => $activeTab === 'contacts'])>Contacts</a>
        </li>
    </ul>

    @if($activeTab === 'policies')
    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-sm obiora-table align-middle mb-0">
                <thead class="obiora-table-head">
                    <tr>
                        <th>Nom</th>
                        <th>Condition</th>
                        <th>Durée</th>
                        <th>Répétition</th>
                        <th>Portée</th>
                        <th>Actif</th>
                        @if($canManage)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($policies as $policy)
                    <tr @class(['opacity-50' => !$policy['is_enabled']])>
                        <td>
                            <span class="fw-medium">{{ $policy['name'] }}</span>
                            @if($policy['description'])
                                <div class="small text-muted">{{ Str::limit($policy['description'], 80) }}</div>
                            @endif
                        </td>
                        <td class="small font-monospace">{{ $policy['condition'] }}</td>
                        <td class="small">{{ $policy['duration'] }}</td>
                        <td class="small">{{ $policy['repeat'] }}</td>
                        <td><span class="badge text-bg-secondary">{{ $policy['apply_to'] }}</span></td>
                        <td>
                            @if($policy['is_enabled'])
                                <span class="badge text-bg-success">Oui</span>
                            @else
                                <span class="badge text-bg-secondary">Non</span>
                            @endif
                        </td>
                        @if($canManage)
                        <td class="text-nowrap">
                            <button type="button" wire:click="openPolicyModal({{ $policy['id'] }})" class="btn btn-outline-secondary btn-sm py-0">Modifier</button>
                            <button type="button" wire:click="togglePolicy({{ $policy['id'] }})" class="btn btn-outline-warning btn-sm py-0">
                                {{ $policy['is_enabled'] ? 'Désactiver' : 'Activer' }}
                            </button>
                            <button type="button" wire:click="deletePolicy({{ $policy['id'] }})" wire:confirm="Supprimer cette politique ?"
                                    class="btn btn-outline-danger btn-sm py-0">Supprimer</button>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $canManage ? 7 : 6 }}" class="text-muted small">Aucune politique. Exécutez le seeder ou créez-en une.</td></tr>
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
                        <th>Nom</th>
                        <th>Canaux</th>
                        @if($canManage)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($contacts as $contact)
                    <tr>
                        <td class="fw-medium">
                            {{ $contact['name'] }}
                            @if($contact['is_default'])
                                <span class="badge text-bg-primary ms-1">Défaut</span>
                            @endif
                        </td>
                        <td>
                            @forelse($contact['channels'] as $channel)
                                <span class="badge text-bg-secondary me-1">{{ $channel }}</span>
                            @empty
                                <span class="text-muted small">Aucun canal configuré</span>
                            @endforelse
                        </td>
                        @if($canManage)
                        <td class="text-nowrap">
                            <button type="button" wire:click="openContactModal({{ $contact['id'] }})" class="btn btn-outline-secondary btn-sm py-0">Modifier</button>
                            @if(!$contact['is_default'])
                            <button type="button" wire:click="deleteContact({{ $contact['id'] }})" wire:confirm="Supprimer ce contact ?"
                                    class="btn btn-outline-danger btn-sm py-0">Supprimer</button>
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $canManage ? 3 : 2 }}" class="text-muted small">Aucun contact.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <p class="small text-muted mt-3 mb-0">Heures en {{ $timezoneFooter }} — {{ $nowLabel }}</p>

    @if($showPolicyModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content obiora-card">
                <div class="modal-header">
                    <h2 class="modal-title h5">{{ $editingPolicyId ? 'Modifier la politique' : 'Nouvelle politique' }}</h2>
                    <button type="button" class="btn-close" wire:click="$set('showPolicyModal', false)"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Nom *</label>
                            <input type="text" wire:model="policyName" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Métrique *</label>
                            <select wire:model.live="policyMetric" class="form-select">
                                @foreach($metricChoices as $choice)
                                    <option value="{{ $choice['value'] }}">{{ $choice['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium">Opérateur</label>
                            <select wire:model="policyOperator" class="form-select">
                                @foreach($operatorChoices as $op)
                                    <option value="{{ $op->value }}">{{ $op->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium">Seuil *</label>
                            <input type="number" step="any" wire:model="policyValue" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium">Unité</label>
                            <input type="text" wire:model="policyValueUnit" class="form-control" placeholder="%, min…">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-medium">Durée (min)</label>
                            <input type="number" min="0" wire:model="policyDurationMinutes" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Répétition (min)</label>
                            <input type="number" min="0" wire:model="policyRepeatMinutes" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-medium">Appliquer à</label>
                            <select wire:model="policyApplyTo" class="form-select">
                                @foreach($applyToChoices as $choice)
                                    <option value="{{ $choice['value'] }}">{{ $choice['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Contacts à notifier *</label>
                            <select wire:model="policyContactIds" class="form-select" multiple size="4">
                                @foreach($allContacts as $contact)
                                    <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Description</label>
                            <textarea wire:model="policyDescription" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="$set('showPolicyModal', false)">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="savePolicy">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($showContactModal)
    <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
        <div class="modal-dialog modal-lg">
            <div class="modal-content obiora-card">
                <div class="modal-header">
                    <h2 class="modal-title h5">{{ $editingContactId ? 'Modifier le contact' : 'Nouveau contact' }}</h2>
                    <button type="button" class="btn-close" wire:click="$set('showContactModal', false)"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Nom *</label>
                            <input type="text" wire:model="contactName" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Email</label>
                            <input type="email" wire:model="contactEmail" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Slack webhook</label>
                            <input type="url" wire:model="contactSlackWebhook" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Discord webhook</label>
                            <input type="url" wire:model="contactDiscordWebhook" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Telegram bot token</label>
                            <input type="text" wire:model="contactTelegramToken" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-medium">Telegram chat ID</label>
                            <input type="text" wire:model="contactTelegramChatId" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-medium">Webhook générique</label>
                            <input type="url" wire:model="contactWebhookUrl" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="$set('showContactModal', false)">Annuler</button>
                    <button type="button" class="btn btn-primary" wire:click="saveContact">Enregistrer</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
