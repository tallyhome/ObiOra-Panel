<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Services</h1>
            <p class="text-muted mb-0">Serveur : <strong>{{ $serverName }}</strong></p>
        </div>
        <button wire:click="refresh" class="btn btn-outline-primary btn-sm" wire:loading.attr="disabled">Actualiser</button>
    </div>

    <div class="card obiora-card mb-3">
        <div class="card-body py-2">
            <input wire:model.live.debounce.300ms="search" type="search" class="form-control form-control-sm obiora-input" placeholder="Rechercher un service...">
        </div>
    </div>

    <div class="card obiora-card">
        <div class="table-responsive" style="max-height: 480px;">
            <table class="table table-sm table-hover obiora-table mb-0">
                <thead class="sticky-top obiora-table-head">
                    <tr>
                        <th>Service</th>
                        <th>État</th>
                        <th>Description</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filtered as $service)
                        <tr>
                            <td class="font-monospace small">{{ $service['name'] }}</td>
                            <td>
                                @php
                                    $badge = match($service['active']) {
                                        'active' => 'success',
                                        'failed' => 'danger',
                                        'inactive' => 'secondary',
                                        default => 'warning',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $badge }}">{{ $service['active'] }}</span>
                            </td>
                            <td class="small text-muted">{{ \Illuminate\Support\Str::limit($service['description'], 50) }}</td>
                            <td class="text-end text-nowrap">
                                <button wire:click="runAction('{{ $service['name'] }}', 'start')" class="btn btn-outline-success btn-sm py-0">Start</button>
                                <button wire:click="runAction('{{ $service['name'] }}', 'stop')" class="btn btn-outline-danger btn-sm py-0">Stop</button>
                                <button wire:click="runAction('{{ $service['name'] }}', 'restart')" class="btn btn-outline-warning btn-sm py-0">Restart</button>
                                <button wire:click="showLogs('{{ $service['name'] }}')" class="btn btn-outline-secondary btn-sm py-0">Logs</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Aucun service trouvé.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($logService)
        <div class="card obiora-card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="small fw-medium">Logs — {{ $logService }}</span>
                <button type="button" class="btn-close btn-sm" wire:click="$set('logService', null)"></button>
            </div>
            <div class="card-body p-0">
                <pre class="small mb-0 p-3 bg-dark text-light" style="max-height: 320px; overflow: auto;">{{ $logOutput ?: 'Aucun log.' }}</pre>
            </div>
        </div>
    @endif
</div>
