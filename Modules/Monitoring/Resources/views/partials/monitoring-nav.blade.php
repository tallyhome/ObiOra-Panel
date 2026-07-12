@php
    $monitoringNav = [
        ['route' => 'monitoring.index', 'label' => 'Dashboard', 'icon' => '▣'],
        ['route' => 'monitoring.servers', 'label' => 'Serveurs', 'icon' => '⬢'],
        ['route' => 'monitoring.monitors', 'label' => 'Moniteurs', 'icon' => '◎'],
        ['route' => 'monitoring.incidents', 'label' => 'Incidents', 'icon' => '⚠'],
        ['route' => 'monitoring.alerts', 'label' => 'Alertes', 'icon' => '🔔'],
        ['route' => 'monitoring.fleet', 'label' => 'Flotte avancée', 'icon' => '◉'],
        ['route' => 'monitoring.preferences', 'label' => 'Préférences', 'icon' => '⚙'],
    ];
@endphp
<nav class="obiora-monitor-nav mb-4" aria-label="Navigation monitoring">
    <ul class="nav nav-pills flex-wrap gap-1">
        @foreach($monitoringNav as $item)
            @php
                $active = request()->routeIs($item['route']);
                $disabled = $item['disabled'] ?? false;
            @endphp
            <li class="nav-item">
                @if($disabled)
                    <span class="nav-link disabled text-muted" title="{{ $item['hint'] ?? '' }}">
                        {{ $item['icon'] }} {{ $item['label'] }}
                        <span class="badge text-bg-secondary ms-1">bientôt</span>
                    </span>
                @else
                    <a href="{{ route($item['route']) }}"
                       @class(['nav-link', 'active' => $active, 'text-muted' => ($item['stub'] ?? false) && !$active])>
                        {{ $item['icon'] }} {{ $item['label'] }}
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
