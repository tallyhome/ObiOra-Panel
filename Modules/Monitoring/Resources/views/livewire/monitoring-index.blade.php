<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Monitoring Obiora</h1>
            <p class="text-muted mb-0">Diagnostics centralises depuis les agents Obiora Doctor.</p>
        </div>
    </div>

    <div class="card obiora-card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Serveur</th>
                        <th>IP</th>
                        <th>Ping</th>
                        <th>Health Score</th>
                        <th>Critiques</th>
                        <th>Dernier rapport</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($servers as $server)
                        <tr>
                            <td>
                                <a href="{{ route('servers.show', $server['id']) }}" class="text-decoration-none fw-semibold">
                                    {{ $server['name'] }}
                                </a>
                            </td>
                            <td class="small">{{ $server['ip'] ?? '—' }}</td>
                            <td>
                                <span class="badge text-bg-{{ $server['status'] === 'online' ? 'success' : 'secondary' }}">
                                    {{ $server['status'] }}
                                </span>
                            </td>
                            <td>
                                @if ($server['score'] !== null)
                                    <span class="badge text-bg-{{ $server['score'] >= 90 ? 'success' : ($server['score'] >= 70 ? 'warning' : 'danger') }}">
                                        {{ $server['score'] }}%
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $server['critical'] ?? 0 }}</td>
                            <td class="small text-muted">{{ $server['report_at'] ?? 'Aucun rapport' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                Aucun serveur. Installez l'agent Obiora sur vos VPS pour recevoir les diagnostics.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card obiora-card mt-4">
        <div class="card-body">
            <h2 class="h6">Installation agent sur un VPS</h2>
            <pre class="small bg-dark text-light p-3 rounded mb-0"><code>OBIORA_PANEL_URL=https://panel.example.com \
OBIORA_SERVER_ID=2 \
OBIORA_AGENT_TOKEN=token_du_serveur \
bash ObiOra-Doctor/install/install-agent.sh</code></pre>
        </div>
    </div>
</div>
