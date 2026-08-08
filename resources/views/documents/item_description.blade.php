{{ $item->description }}
@if (!empty($item->scope_items))
    <div class="scope-label">Including:</div>
    <ul class="scope-list">
        @foreach ($item->scope_items as $scopeItem)
            <li>{{ $scopeItem }}</li>
        @endforeach
    </ul>
@endif
