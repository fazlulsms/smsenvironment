<tr>
    <td><select class="form-select form-select-sm" name="items[__INDEX__][service_id]" data-service-select><option value="">Custom</option>@foreach ($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select></td>
    <td><textarea class="form-control form-control-sm" rows="2" name="items[__INDEX__][description]" data-description></textarea></td>
    <td><input class="form-control form-control-sm" name="items[__INDEX__][unit]" value="Job" data-unit></td>
    <td><input class="form-control form-control-sm text-end calc" type="number" step="0.01" name="items[__INDEX__][quantity]" value="1" data-qty required></td>
    <td><input class="form-control form-control-sm text-end calc" type="number" step="0.01" name="items[__INDEX__][unit_rate]" value="0" data-rate required></td>
    <td class="text-end fw-semibold" data-amount>0.00</td>
    <td><button class="btn btn-sm btn-outline-danger" type="button" data-remove-row>Remove</button></td>
</tr>
