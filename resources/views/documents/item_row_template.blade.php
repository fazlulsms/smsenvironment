<tr>
    <td><select class="form-select form-select-sm" name="items[__INDEX__][service_id]" data-service-select><option value="">Custom</option>@foreach ($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select></td>
    <td><textarea class="form-control form-control-sm" rows="2" name="items[__INDEX__][description]" data-description placeholder="Service / particular"></textarea><textarea class="form-control form-control-sm mt-1" rows="2" name="items[__INDEX__][scope_items]" data-scope-items placeholder="Optional “Including:” scope, one per line"></textarea></td>
    <td><input class="form-control form-control-sm num amount-input" type="number" step="0.01" min="0" name="items[__INDEX__][amount]" value="0" data-amount-input required></td>
    <td><button class="btn-icon" type="button" data-remove-row title="Remove"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button></td>
</tr>
