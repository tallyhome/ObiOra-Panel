@php
    $size = $size ?? 56;
    $class = $class ?? '';
    $iconUrl = $package->iconUrl();
    $fallback = $package->iconFallbackDataUri();
@endphp
<img
    src="{{ $iconUrl }}"
    alt=""
    width="{{ $size }}"
    height="{{ $size }}"
    class="obiora-marketplace-icon {{ $class }}"
    loading="lazy"
    decoding="async"
    onerror="this.onerror=null;this.src='{{ $fallback }}'"
>
