@csrf
<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="services" /></span><div><div class="fs-t">Service Details</div><div class="fs-s">Name, type, default pricing and reusable document wording.</div></div></div>
    <div class="fs-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Service Name</label><input class="form-control" name="name" value="{{ old('name', $service->name) }}" required></div>
            <div class="col-md-3"><label class="form-label">Short / Display Name</label><input class="form-control" name="short_name" value="{{ old('short_name', $service->short_name) }}"></div>
            <div class="col-md-3"><label class="form-label">Category</label><input class="form-control" name="category" value="{{ old('category', $service->category) }}"></div>
            <div class="col-md-6">
                <label class="form-label">Service Type</label>
                <select class="form-select" name="service_type">
                    @foreach (\App\Models\Service::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('service_type', $service->service_type ?? \App\Models\Service::TYPE_STANDALONE) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="form-hint">Bundles &amp; consolidated services can carry scope components below.</div>
            </div>
            <div class="col-md-3"><label class="form-label">Default Unit</label><input class="form-control" name="default_unit" value="{{ old('default_unit', $service->default_unit) }}"></div>
            <div class="col-md-3"><label class="form-label">Default Rate</label><input class="form-control" type="number" step="0.01" name="default_rate" value="{{ old('default_rate', $service->default_rate ?? 0) }}" required></div>
            <div class="col-12"><label class="form-label">Default Commercial Description</label><textarea class="form-control" name="default_description">{{ old('default_description', $service->default_description) }}</textarea></div>
            <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active ?? true))> <span class="form-check-label">Active</span></label></div>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="dashboard" /></span><div><div class="fs-t">Available For</div><div class="fs-s">Which business entities may use this shared service.</div></div></div>
    <div class="fs-body">
        <div class="d-flex flex-wrap gap-3">
            @foreach ($entities as $entity)
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="entities[]" value="{{ $entity->id }}"
                        @checked(in_array($entity->id, old('entities', $enabledEntityIds ?? []), false))>
                    <span class="form-check-label">{{ $entity->name }}</span>
                </label>
            @endforeach
        </div>
    </div>
</div>

<details class="adv" {{ $errors->any() ? 'open' : '' }}>
    <summary><x-icon name="edit" :size="16" /> Document Wording <span class="text-secondary fw-normal ms-2" style="font-size:12px">Subject, scope, compliance &amp; invoice text</span><span class="chev"><x-icon name="chevron-left" :size="14" style="transform:rotate(-90deg)" /></span></summary>
    <div class="adv-body">
        <div class="row g-3">
            <div class="col-12"><label class="form-label">Quotation Subject Template</label><input class="form-control" name="quotation_subject_template" value="{{ old('quotation_subject_template', $service->quotation_subject_template) }}"></div>
            <div class="col-12"><label class="form-label">Quotation Scope / Description</label><textarea class="form-control" name="quotation_scope">{{ old('quotation_scope', $service->quotation_scope) }}</textarea></div>
            <div class="col-12"><label class="form-label">Applicable Standards / Compliance Note</label><textarea class="form-control" name="compliance_note">{{ old('compliance_note', $service->compliance_note) }}</textarea></div>
            <div class="col-12"><label class="form-label">Invoice Description / Charge For</label><textarea class="form-control" name="invoice_description">{{ old('invoice_description', $service->invoice_description) }}</textarea></div>
        </div>
    </div>
</details>

<div class="form-section">
    <div class="fs-head">
        <span class="fs-ico"><x-icon name="services" /></span>
        <div><div class="fs-t">Package / Scope Components</div><div class="fs-s">Optional components shown under “Including” for bundles.</div></div>
        <button class="btn btn-outline-primary btn-sm ms-auto" type="button" id="addComponent"><x-icon name="plus" :size="15" /> Add Component</button>
    </div>
    <div class="fs-body">
        <div class="table-responsive">
            <table class="table align-middle" id="componentsTable">
                <thead><tr><th style="width:24%">Name</th><th>Description</th><th style="width:12%" class="num">Default Price</th><th style="width:9%">Order</th><th style="width:9%">Active</th><th></th></tr></thead>
                <tbody>
                @php($components = old('components', $service->components?->map(fn ($component) => $component->only(['name', 'description', 'default_price', 'sort_order', 'is_active']))->toArray() ?? []))
                @forelse ($components as $index => $component)
                    <tr>
                        <td><input class="form-control form-control-sm" name="components[{{ $index }}][name]" value="{{ $component['name'] ?? '' }}"></td>
                        <td><textarea class="form-control form-control-sm" rows="2" name="components[{{ $index }}][description]">{{ $component['description'] ?? '' }}</textarea></td>
                        <td><input class="form-control form-control-sm num" type="number" step="0.01" name="components[{{ $index }}][default_price]" value="{{ $component['default_price'] ?? '' }}"></td>
                        <td><input class="form-control form-control-sm num" type="number" name="components[{{ $index }}][sort_order]" value="{{ $component['sort_order'] ?? $index + 1 }}"></td>
                        <td class="text-center"><input class="form-check-input" type="checkbox" name="components[{{ $index }}][is_active]" value="1" @checked($component['is_active'] ?? true)></td>
                        <td><button class="btn-icon" type="button" data-remove-component title="Remove"><x-icon name="trash" :size="15" /></button></td>
                    </tr>
                @empty
                    {{-- rows added dynamically --}}
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="form-hint mb-0" id="componentsEmptyHint" style="{{ count($components) ? 'display:none' : '' }}">No components yet. Standalone services don’t need any — add components for bundles.</p>
    </div>
</div>

<div class="d-flex gap-2 justify-content-end">
    <a class="btn btn-outline-secondary" href="{{ route('services.index') }}">Cancel</a>
    <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ $service->exists ? 'Update Service' : 'Save Service' }}</button>
</div>

@push('scripts')
<script>
const componentsBody = document.querySelector('#componentsTable tbody');
let nextComponentIndex = componentsBody.querySelectorAll('tr').length;

function componentRow(index) {
    return `<tr>
        <td><input class="form-control form-control-sm" name="components[${index}][name]"></td>
        <td><textarea class="form-control form-control-sm" rows="2" name="components[${index}][description]"></textarea></td>
        <td><input class="form-control form-control-sm num" type="number" step="0.01" name="components[${index}][default_price]"></td>
        <td><input class="form-control form-control-sm num" type="number" name="components[${index}][sort_order]" value="${index + 1}"></td>
        <td class="text-center"><input class="form-check-input" type="checkbox" name="components[${index}][is_active]" value="1" checked></td>
        <td><button class="btn-icon" type="button" data-remove-component title="Remove"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button></td>
    </tr>`;
}

document.getElementById('addComponent').addEventListener('click', () => {
    componentsBody.insertAdjacentHTML('beforeend', componentRow(nextComponentIndex));
    nextComponentIndex++;
    const hint = document.getElementById('componentsEmptyHint');
    if (hint) hint.style.display = 'none';
});

document.addEventListener('click', event => {
    if (event.target.closest('[data-remove-component]')) {
        event.target.closest('tr').remove();
    }
});
</script>
@endpush
