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
        <textarea class="form-control form-control-sm" rows="2" name="items[{{ $index }}][description]" data-description>{{ $item['description'] ?? '' }}</textarea>
        <textarea class="form-control form-control-sm mt-1" rows="3" name="items[{{ $index }}][scope_items]" data-scope-items placeholder="Scope items / package inclusions, one per line">{{ $item['scope_items'] ?? '' }}</textarea>
        <select class="form-select form-select-sm mt-1" name="items[{{ $index }}][pricing_mode]" data-pricing-mode>
            <option value="separate" @selected(($item['pricing_mode'] ?? 'separate') === 'separate')>Separate commercial line</option>
            <option value="consolidated" @selected(($item['pricing_mode'] ?? 'separate') === 'consolidated')>One package price</option>
        </select>
    </td>
    <td><input class="form-control form-control-sm" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? '' }}" data-unit></td>
    <td><input class="form-control form-control-sm text-end calc" type="number" step="0.01" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" data-qty required></td>
    <td><input class="form-control form-control-sm text-end calc" type="number" step="0.01" name="items[{{ $index }}][unit_rate]" value="{{ $item['unit_rate'] ?? 0 }}" data-rate required></td>
    <td class="text-end fw-semibold" data-amount>0.00</td>
    <td><button class="btn btn-sm btn-outline-danger" type="button" data-remove-row>Remove</button></td>
</tr>
