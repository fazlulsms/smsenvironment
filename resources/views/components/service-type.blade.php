@props(['type' => 'standalone'])
@php
    $map = [
        'standalone'   => ['b-info', 'Standalone'],
        'bundle'       => ['b-service', 'Bundle / Package'],
        'consolidated' => ['b-quote', 'Consolidated'],
    ];
    [$cls, $label] = $map[$type] ?? ['b-neutral', ucfirst($type)];
@endphp
<span class="badge-soft {{ $cls }}"><span class="dotmark"></span>{{ $label }}</span>
