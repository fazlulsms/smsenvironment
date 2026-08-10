@props([
    'label' => '',
    'value' => '0',
    'unit' => null,
    'icon' => 'dashboard',
    'theme' => 'brand',
    'href' => null,
    'foot' => null,
])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="stat-card t-{{ $theme }}">
    <span class="sc-accent"></span>
    <div class="sc-top">
        <span class="sc-label">{{ $label }}</span>
        <span class="sc-ico"><x-icon :name="$icon" :size="20" /></span>
    </div>
    <div class="sc-value">@if($unit)<span class="unit">{{ $unit }}</span>@endif{{ $value }}</div>
    @if ($foot)<div class="sc-foot">{{ $foot }}</div>@endif
</{{ $tag }}>
