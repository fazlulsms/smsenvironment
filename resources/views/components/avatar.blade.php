@props(['user', 'size' => 36])

@php
    $url = $user?->avatarUrl();
    $dim = (int) $size;
@endphp

<span {{ $attributes->merge(['class' => 'smsea-avatar']) }}
      style="width:{{ $dim }}px;height:{{ $dim }}px;font-size:{{ max(11, (int) round($dim * 0.4)) }}px">
    @if ($url)
        <img src="{{ $url }}" alt="{{ $user->name }}" width="{{ $dim }}" height="{{ $dim }}">
    @else
        {{ $user?->initials() ?? 'U' }}
    @endif
</span>
