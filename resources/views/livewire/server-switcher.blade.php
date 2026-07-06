<div class="dropdown">
    <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
        @php
            $current = collect($servers)->firstWhere('id', $currentId);
        @endphp
        {{ $current['name'] ?? 'Serveur' }}
        @if ($current)
            <span class="badge ms-1 {{ $current['status'] === 'online' ? 'text-bg-success' : 'text-bg-secondary' }}">
                {{ $current['status'] }}
            </span>
        @endif
    </button>
    <ul class="dropdown-menu">
        @foreach ($servers as $server)
            <li>
                <button type="button" class="dropdown-item {{ $server['id'] === $currentId ? 'active' : '' }}"
                    wire:click="switchServer({{ $server['id'] }})">
                    {{ $server['name'] }}
                    @if ($server['is_master'])
                        <span class="badge text-bg-primary ms-1">maître</span>
                    @endif
                </button>
            </li>
        @endforeach
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="{{ route('servers.create') }}">+ Ajouter un serveur</a></li>
    </ul>
</div>
