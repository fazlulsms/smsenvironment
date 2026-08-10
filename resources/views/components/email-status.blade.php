@props(['deliveries' => null])
@php
    $items = $deliveries ? collect($deliveries) : collect();
    $latest = $items->sortByDesc(fn ($d) => $d->created_at)->first();
    $sentCount = $items->where('status', 'sent')->count();
@endphp
@if (! $latest)
    <span class="badge-soft b-neutral" title="No email sent yet"><span class="dotmark"></span>Not sent</span>
@elseif ($latest->status === 'sent')
    <span class="badge-soft b-ok" title="Emailed to {{ $latest->to_email }}">
        <x-icon name="check" :size="12" />Sent{{ $sentCount > 1 ? ' ·'.$sentCount : '' }}
    </span>
@else
    <span class="badge-soft b-danger" title="Last send failed">
        <x-icon name="alert" :size="12" />Failed
    </span>
@endif
