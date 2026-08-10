@props(['title' => null, 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'page-toolbar']) }}>
    @if ($title)
        <div>
            <h2 class="pt-title">{{ $title }}</h2>
            @if ($subtitle)<p class="pt-sub">{{ $subtitle }}</p>@endif
        </div>
    @endif
    {{ $slot }}
    @isset($actions)
        <div class="pt-actions">{{ $actions }}</div>
    @endisset
</div>
