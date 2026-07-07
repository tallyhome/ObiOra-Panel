<div>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1 fw-bold">
                <span class="me-2">{{ $module['icon'] ?? '◫' }}</span>{{ $module['name'] ?? $slug }}
            </h1>
            <p class="text-muted mb-0">{{ $module['description'] ?? '' }}</p>
        </div>
        @if ($moduleEnabled)
            <span class="badge text-bg-success">Module actif</span>
        @else
            <span class="badge text-bg-secondary">Module desactive</span>
        @endif
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card obiora-card">
                <div class="card-body">
                    <h2 class="h6">Apercu Phase 11</h2>
                    <p class="mb-3">{{ $module['planned'] ?? 'Fonctionnalite planifiee.' }}</p>
                    <div class="alert alert-info small mb-0">
                        Ce module dispose desormais d'une page dediee dans le panel seedbox.
                        L'implementation metier complete arrive dans les prochaines releases
                        (Phase 12 pour l'assistant IA).
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card obiora-card h-100">
                <div class="card-body">
                    <h2 class="h6">Liens utiles</h2>
                    @if (!empty($module['links']))
                        <ul class="list-unstyled mb-0">
                            @foreach ($module['links'] as $link)
                                <li class="mb-2">
                                    <a href="{{ route($link['route']) }}" class="text-decoration-none">
                                        → {{ $link['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-muted small mb-0">Aucun lien associe pour le moment.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
