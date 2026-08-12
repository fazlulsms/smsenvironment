<tr>
    <td>
        <select class="form-select form-select-sm" name="items[{{ $index }}][service_id]" data-service-select>
            <option value="">Custom</option>
            @foreach ($services as $service)
                <option value="{{ $service->id }}" @selected(($item['service_id'] ?? '') == $service->id)>{{ $service->name }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <textarea class="form-control form-control-sm" rows="2" name="items[{{ $index }}][description]" data-description placeholder="Service / particular">{{ $item['description'] ?? '' }}</textarea>
        <textarea class="form-control form-control-sm mt-1" rows="2" name="items[{{ $index }}][scope_items]" data-scope-items placeholder="Optional “Including:” scope, one per line">{{ $item['scope_items'] ?? '' }}</textarea>
    </td>
    <td><input class="form-control form-control-sm num amount-input" type="number" step="0.01" min="0" name="items[{{ $index }}][amount]" value="{{ $item['amount'] ?? ($item['unit_rate'] ?? 0) }}" data-amount-input required></td>
    <td><button class="btn-icon" type="button" data-remove-row title="Remove"><x-icon name="trash" :size="15" /></button></td>
</tr>
