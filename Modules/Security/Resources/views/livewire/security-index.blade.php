<div>
    <div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1 class="h3 mb-1">Sécurité serveur</h1>
            <p class="text-muted mb-0">
                Audit intégré à ObiOra Doctor — serveur panel et slaves Obiora uniquement.
                Scans SSH, pare-feu, agent, rootkits (rkhunter/chkrootkit) et plan de durcissement.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('doctor.index', ['server' => $serverId]) }}" class="btn btn-outline-secondary btn-sm">Doctor & Suite</a>
            <a href="{{ route('firewall.index') }}" class="btn btn-outline-secondary btn-sm">Firewall</a>
        </div>
    </div>

    @if($eligibleServers->isEmpty())
        <div class="alert alert-warning">
            Aucun serveur éligible. Ajoutez le serveur panel (maître) ou un slave avec agent Obiora installé.
        </div>
    @else
        <div class="card obiora-card mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label small fw-medium">Serveur à auditer</label>
                        <select wire:model.live="serverId" class="form-select">
                            @foreach($eligibleServers as $s)
                                <option value="{{ $s->id }}">
                                    {{ $s->name }}
                                    @if($s->is_master) (Panel) @else (Slave) @endif
                                    — {{ $s->ip_address }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-7 d-flex gap-2 flex-wrap align-items-end">
                        @if($canManage)
                            <button type="button"
                                    wire:click="runScan"
                                    wire:loading.attr="disabled"
                                    @disabled($scanning)
                                    class="btn btn-primary btn-sm">
                                <span wire:loading.remove wire:target="runScan">Lancer un scan</span>
                                <span wire:loading wire:target="runScan">Lancement…</span>
                            </button>
                        @endif
                        <button type="button" wire:click="refreshAudit" class="btn btn-outline-secondary btn-sm">
                            Actualiser
                        </button>
                    </div>
                </div>

                @if($scanning)
                <div class="mt-3" wire:poll.2s="pollScanProgress">
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="fw-medium">{{ $scanProgress['message'] ?? 'Scan en cours…' }}</span>
                        <span>{{ $scanProgress['progress'] ?? 0 }}%</span>
                    </div>
                    <div class="progress mb-2" style="height:10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             style="width: {{ min(100, max(0, (int) ($scanProgress['progress'] ?? 0))) }}%"></div>
                    </div>
                    @if(!empty($scanProgress['output']))
                        <pre class="small bg-dark text-light p-2 rounded mb-0" style="max-height:120px;overflow:auto;">{{ \Illuminate\Support\Str::limit($scanProgress['output'], 800) }}</pre>
                    @else
                        <p class="small text-muted mb-0">Modules analysés : SSH, pare-feu, utilisateurs, services, rootkits (rkhunter/chkrootkit), agent Doctor…</p>
                    @endif
                    @if(str_contains((string) ($scanProgress['message'] ?? ''), 'obiora-queue'))
                        <div class="alert alert-warning small mt-2 mb-0 py-2">
                            Le scan est en file d'attente. Sans worker actif, rien ne s'exécute :
                            <code class="d-block mt-1">sudo systemctl enable --now obiora-queue</code>
                        </div>
                    @endif
                </div>
                @endif

                @if($actionMessage && !$scanning)
                    <div class="mt-3 small {{ $actionOk ? 'text-success' : 'text-danger' }}">{{ $actionMessage }}</div>
                @endif
            </div>
        </div>

        @if($server)
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card obiora-card h-100 text-center">
                        <div class="card-body">
                            <div class="display-6 fw-bold {{ ($audit['security_score'] ?? 0) >= 70 ? 'text-success' : (($audit['security_score'] ?? 0) >= 40 ? 'text-warning' : 'text-danger') }}">
                                {{ $audit['security_score'] ?? '—' }}@if(isset($audit['security_score']))%@endif
                            </div>
                            <div class="small text-muted">Score sécurité</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card obiora-card h-100 text-center">
                        <div class="card-body">
                            <div class="display-6 fw-bold text-danger">{{ $audit['critical_count'] ?? 0 }}</div>
                            <div class="small text-muted">Critiques</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card obiora-card h-100 text-center">
                        <div class="card-body">
                            <div class="display-6 fw-bold text-warning">{{ $audit['warning_count'] ?? 0 }}</div>
                            <div class="small text-muted">Avertissements</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card obiora-card h-100 text-center">
                        <div class="card-body">
                            @php $st = $audit['status'] ?? 'unknown'; @endphp
                            <span class="badge fs-6 {{ $st === 'ok' ? 'text-bg-success' : ($st === 'critical' ? 'text-bg-danger' : 'text-bg-warning') }}">
                                {{ strtoupper($st) }}
                            </span>
                            <div class="small text-muted mt-2">
                                @if($audit['generated_at'] ?? null)
                                    Dernier scan {{ \Illuminate\Support\Carbon::parse($audit['generated_at'])->diffForHumans() }}
                                @else
                                    Aucun scan
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if(!($audit['has_report'] ?? false))
                <div class="alert alert-info">
                    {{ $audit['message'] ?? 'Installez l\'agent Doctor ou lancez un scan pour obtenir l\'audit sécurité.' }}
                </div>
            @else
                {{-- Plan de durcissement --}}
                @if(!empty($audit['plan']))
                    <div class="card obiora-card mb-4">
                        <div class="card-header fw-semibold">Plan de sécurisation</div>
                        <div class="card-body">
                            @foreach($audit['plan'] as $group)
                                <div class="mb-4">
                                    <h3 class="h6 {{ $group['priority'] === 'P0' ? 'text-danger' : ($group['priority'] === 'P1' ? 'text-warning' : '') }}">
                                        {{ $group['priority'] }} — {{ $group['label'] }}
                                    </h3>
                                    <ul class="list-unstyled mb-0">
                                        @foreach($group['items'] as $item)
                                            <li class="border-bottom border-secondary border-opacity-25 py-2">
                                                <div class="d-flex justify-content-between align-items-start gap-2">
                                                    <div>
                                                        <strong>{{ $item['title'] }}</strong>
                                                        <span class="badge text-bg-secondary ms-1">{{ $item['module'] }}</span>
                                                        <p class="small text-muted mb-1">{{ $item['details'] }}</p>
                                                        <p class="small mb-0">{{ $item['recommendation'] }}</p>
                                                    </div>
                                                    @if($canManage && !empty($item['harden_action']))
                                                        <button type="button"
                                                                wire:click="applyHardening('{{ $item['harden_action'] }}')"
                                                                wire:confirm="Appliquer le durcissement « {{ $item['harden_action'] }} » ? Une sauvegarde sera créée."
                                                                class="btn btn-outline-success btn-sm flex-shrink-0">
                                                            Corriger
                                                        </button>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Findings détaillés --}}
                @if(!empty($audit['findings']))
                    <div class="card obiora-card mb-4">
                        <div class="card-header fw-semibold">Findings sécurité</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-dark mb-0">
                                <thead>
                                    <tr>
                                        <th>Niveau</th>
                                        <th>Module</th>
                                        <th>Problème</th>
                                        <th>Recommandation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($audit['findings'] as $finding)
                                        <tr>
                                            <td>
                                                <span class="badge {{ $finding['level'] === 'CRITICAL' ? 'text-bg-danger' : 'text-bg-warning' }}">
                                                    {{ $finding['level'] }}
                                                </span>
                                            </td>
                                            <td><code>{{ $finding['module'] }}</code></td>
                                            <td>
                                                <strong>{{ $finding['title'] }}</strong>
                                                <div class="small text-muted">{{ $finding['details'] }}</div>
                                            </td>
                                            <td class="small">{{ $finding['recommendation'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Modules scannés --}}
                @if(!empty($audit['modules']))
                    <div class="card obiora-card mb-4">
                        <div class="card-header fw-semibold">Modules Doctor (sécurité)</div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($audit['modules'] as $mod)
                                    <span class="badge text-bg-dark border border-secondary">
                                        {{ $mod['module'] }}
                                        @if(isset($mod['score'])) — {{ $mod['score'] }}% @endif
                                        ({{ $mod['findings_count'] }} findings)
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            @endif

            {{-- Actions durcissement manuelles --}}
            @if($canManage)
                <div class="card obiora-card mb-4">
                    <div class="card-header fw-semibold">Durcissement manuel</div>
                    <div class="card-body">
                        <p class="small text-muted">Chaque action crée une sauvegarde dans <code>/var/backups/obiora-security/</code> avant modification.</p>
                        <div class="row g-2">
                            @foreach($hardenActions as $action)
                                <div class="col-md-6 col-lg-4">
                                    <div class="border border-secondary border-opacity-25 rounded p-3 h-100">
                                        <div class="fw-medium">{{ $action['label'] }}</div>
                                        <div class="small text-muted mb-2">{{ $action['description'] }}</div>
                                        <button type="button"
                                                wire:click="applyHardening('{{ $action['id'] }}')"
                                                wire:confirm="Confirmer : {{ $action['label'] }} ?"
                                                class="btn btn-sm btn-outline-primary">
                                            Appliquer
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endif

        {{-- Vue flotte --}}
        @if(count($fleet) > 1)
            <div class="card obiora-card">
                <div class="card-header fw-semibold">Flotte — vue sécurité</div>
                <div class="table-responsive">
                    <table class="table table-sm table-dark mb-0">
                        <thead>
                            <tr>
                                <th>Serveur</th>
                                <th>Type</th>
                                <th>Score</th>
                                <th>Critiques</th>
                                <th>Warnings</th>
                                <th>Dernier scan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fleet as $row)
                                <tr wire:key="fleet-{{ $row['id'] }}" class="{{ $row['id'] === $serverId ? 'table-active' : '' }}">
                                    <td>
                                        <button type="button" wire:click="$set('serverId', {{ $row['id'] }})" class="btn btn-link btn-sm p-0 text-decoration-none">
                                            {{ $row['name'] }}
                                        </button>
                                    </td>
                                    <td>{{ $row['is_master'] ? 'Panel' : 'Slave' }}</td>
                                    <td>
                                        @if($row['security_score'] !== null)
                                            <span class="{{ $row['security_score'] >= 70 ? 'text-success' : 'text-warning' }}">{{ $row['security_score'] }}%</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-danger">{{ $row['critical_count'] }}</td>
                                    <td class="text-warning">{{ $row['warning_count'] }}</td>
                                    <td class="small text-muted">
                                        @if($row['generated_at'])
                                            {{ \Illuminate\Support\Carbon::parse($row['generated_at'])->diffForHumans() }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
