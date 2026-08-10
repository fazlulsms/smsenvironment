@props(['icon' => 'quotation', 'title' => 'Nothing here yet', 'message' => null])
<div class="empty-state">
    <div class="es-ico"><x-icon :name="$icon" :size="26" /></div>
    <h3>{{ $title }}</h3>
    @if ($message)<p>{{ $message }}</p>@endif
    @if (! $slot->isEmpty())<div>{{ $slot }}</div>@endif
</div>
