<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('obiora.name') }}</title>
    @vite(['resources/scss/obiora.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="obiora-app">
    <div class="d-flex" style="min-height: 100vh;">
        @include('partials.sidebar')

        <div class="flex-grow-1 d-flex flex-column">
            @include('partials.navbar')

            <main class="p-4 obiora-main flex-grow-1">
                @if (session('success'))
                    <span id="obiora-flash-success" class="d-none" data-message="{{ session('success') }}"></span>
                @endif
                @if (session('error'))
                    <span id="obiora-flash-error" class="d-none" data-message="{{ session('error') }}"></span>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>
</html>
