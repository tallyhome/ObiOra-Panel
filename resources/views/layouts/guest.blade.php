<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-obiora-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('obiora.name') }}</title>
    <script>
        (function () {
            var t = localStorage.getItem('obiora-theme') || 'dark';
            document.documentElement.setAttribute('data-obiora-theme', t);
        })();
    </script>
    @vite(['resources/scss/obiora.scss', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="obiora-guest d-flex align-items-center" style="min-height: 100vh;">
    <div class="position-fixed top-0 end-0 p-3 d-flex gap-2">
        <div class="btn-group btn-group-sm obiora-locale-switch" role="group" aria-label="{{ __('panel.nav.language') }}">
            @foreach(config('obiora.locales', ['fr', 'en']) as $loc)
            <a href="{{ route('locale', $loc) }}"
               class="btn btn-outline-secondary {{ app()->getLocale() === $loc ? 'active' : '' }}">{{ strtoupper($loc) }}</a>
            @endforeach
        </div>
        <button type="button"
                class="btn btn-outline-secondary btn-sm"
                data-obiora-theme-toggle
                title="{{ __('panel.nav.theme_light') }} / {{ __('panel.nav.theme_dark') }}"
                aria-label="{{ __('panel.nav.theme_light') }}">
            <span class="obiora-theme-icon-dark">☀</span>
            <span class="obiora-theme-icon-light d-none">☾</span>
        </button>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="text-center mb-4">
                    @include('partials.logo')
                    <p class="text-muted small mt-2 mb-0">v{{ config('obiora.version') }}</p>
                </div>
                {{ $slot }}
            </div>
        </div>
    </div>
    @livewireScripts
    <script>
        document.querySelectorAll('[data-obiora-theme-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (window.obioraToggleTheme) {
                    window.obioraToggleTheme();
                }
            });
        });
    </script>
</body>
</html>
