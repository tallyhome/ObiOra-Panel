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
    <script>
        window.obioraRealtime = @json(array_merge(
            \App\Support\Realtime::clientConfig(),
            ['serverId' => app(\App\Services\Core\ServerManager::class)->getCurrentServer()?->id]
        ));
    </script>
</head>
<body class="obiora-app">
    <div class="d-flex" style="min-height: 100vh;">
        @include('partials.sidebar')

        <div class="flex-grow-1 d-flex flex-column">
            @include('partials.navbar')

            <main class="p-4 obiora-main flex-grow-1">
                @auth
                    @if(auth()->user()->is_demo && auth()->user()->demo_expires_at)
                        <div class="alert alert-info py-2 mb-3 small" role="status">
                            {{ __('panel.demo.banner', ['date' => auth()->user()->demoExpiresAtLabel()]) }}
                            @if(auth()->user()->demoRemainingLabel() !== '')
                                <span class="ms-1">{{ __('panel.demo.remaining', ['time' => auth()->user()->demoRemainingLabel()]) }}</span>
                            @endif
                            @if(auth()->user()->demoExpiresInMinutes() !== null && auth()->user()->demoExpiresInMinutes() <= 30)
                                <strong class="ms-1">{{ __('panel.demo.soon') }}</strong>
                            @endif
                        </div>
                    @endif
                @endauth
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
