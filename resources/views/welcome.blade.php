<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} — v{{ $version }}</title>
    @vite(['resources/scss/obiora.scss', 'resources/js/app.js'])
</head>
<body>
    <nav class="navbar navbar-dark obiora-navbar mb-4">
        <div class="container">
            <span class="navbar-brand fw-semibold">{{ $appName }}</span>
            <span class="badge bg-light text-dark obiora-badge-version">v{{ $version }}</span>
        </div>
    </nav>

    <main class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card obiora-card">
                    <div class="card-body p-5 text-center">
                        <h1 class="h3 mb-3">Architecture Phase 1 initialisée</h1>
                        <p class="text-muted mb-4">
                            Le noyau ObiOra Panel est prêt. Les modules, migrations et services core sont en place.
                            Le dashboard complet arrive en Phase 3 (v1.2.0).
                        </p>
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <span class="badge text-bg-primary">Laravel 12</span>
                            <span class="badge text-bg-secondary">Livewire 3</span>
                            <span class="badge text-bg-success">Bootstrap 5.3</span>
                            <span class="badge text-bg-info">ApexCharts</span>
                        </div>
                        <hr class="my-4">
                        <p class="small text-muted mb-0">
                            API : <code>/api/v1/health</code>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
