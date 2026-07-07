<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('obiora.name') }}</title>
    @vite(['resources/scss/obiora.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="obiora-guest d-flex align-items-center" style="min-height: 100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="text-center mb-4">
                    <h1 class="h3 fw-bold" style="color: var(--obiora-primary)">{{ config('obiora.name') }}</h1>
                    <p class="text-muted small">Seedbox Panel · v{{ config('obiora.version') }}</p>
                </div>
                {{ $slot }}
            </div>
        </div>
    </div>
    @livewireScripts
</body>
</html>
