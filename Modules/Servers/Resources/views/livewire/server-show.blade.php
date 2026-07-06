        @unless ($server->is_master)
            <div class="col-lg-6">
                <div class="card obiora-card">
                    <div class="card-body">
                        <h2 class="h6">Agent slave</h2>
                        <p class="small text-muted mb-2">Ce serveur a été lié via la clé API générée par <code>Slave/install.sh</code>.</p>
                        <dl class="row small mb-0">
                            <dt class="col-4">Port</dt>
                            <dd class="col-8">{{ $server->primaryNode?->port ?? 9100 }}</dd>
                            <dt class="col-4">Connexion</dt>
                            <dd class="col-8">{{ $server->primaryNode?->connection_type ?? 'agent' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        @endunless