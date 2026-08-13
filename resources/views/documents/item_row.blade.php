<tr>
    <td>
        <textarea class="form-control form-control-sm" rows="2" name="items[{{ $index }}][description]" data-description placeholder="Service / particular (multiline allowed)">{{ $item['description'] ?? '' }}</textarea>
    </td>
    <td><input class="form-control form-control-sm num amount-input" type="number" step="0.01" min="0" name="items[{{ $index }}][amount]" value="{{ $item['amount'] ?? ($item['unit_rate'] ?? 0) }}" data-amount-input required></td>
    <td><button class="btn-icon" type="button" data-remove-row title="Remove"><x-icon name="trash" :size="15" /></button></td>
</tr>
