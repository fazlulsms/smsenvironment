@csrf
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
    </div>
    <div class="col-md-6"><label class="form-label">Default Commercial Description</label><textarea class="form-control" name="default_description">{{ old('default_description', $service->default_description) }}</textarea></div>
    <div class="col-md-6"><label class="form-label">Default Unit</label><input class="form-control" name="default_unit" value="{{ old('default_unit', $service->default_unit) }}"></div>
    <div class="col-md-6"><label class="form-label">Default Rate</label><input class="form-control" type="number" step="0.01" name="default_rate" value="{{ old('default_rate', $service->default_rate ?? 0) }}" required></div>
    <div class="col-12"><label class="form-label">Quotation Subject Template</label><input class="form-control" name="quotation_subject_template" value="{{ old('quotation_subject_template', $service->quotation_subject_template) }}"></div>
    <div class="col-12"><label class="form-label">Quotation Scope / Description</label><textarea class="form-control" name="quotation_scope">{{ old('quotation_scope', $service->quotation_scope) }}</textarea></div>
    <div class="col-12"><label class="form-label">Applicable Standards / Compliance Note</label><textarea class="form-control" name="compliance_note">{{ old('compliance_note', $service->compliance_note) }}</textarea></div>
    <div class="col-12"><label class="form-label">Invoice Description / Charge For</label><textarea class="form-control" name="invoice_description">{{ old('invoice_description', $service->invoice_description) }}</textarea></div>
    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active ?? true))> <span class="form-check-label">Active</span></label></div>
</div>
<div class="panel p-3 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Package / Scope Components</h2>
        <button class="btn btn-sm btn-outline-primary" type="button" id="addComponent">Add Component</button>
    </div>
    <div class="table-responsive">
        <table class="table align-middle" id="componentsTable">
            <thead><tr><th style="width:24%">Name</th><th>Description</th><th style="width:12%">Default Price</th><th style="width:9%">Order</th><th style="width:9%">Active</th><th></th></tr></thead>
            <tbody>
            @php($components = old('components', $service->components?->map(fn ($component) => $component->only(['name', 'description', 'default_price', 'sort_order', 'is_active']))->toArray() ?? []))
            @foreach ($components as $index => $component)
                <tr>
                    <td><input class="form-control form-control-sm" name="components[{{ $index }}][name]" value="{{ $component['name'] ?? '' }}"></td>
                    <td><textarea class="form-control form-control-sm" rows="2" name="components[{{ $index }}][description]">{{ $component['description'] ?? '' }}</textarea></td>
                    <td><input class="form-control form-control-sm text-end" type="number" step="0.01" name="components[{{ $index }}][default_price]" value="{{ $component['default_price'] ?? '' }}"></td>
                    <td><input class="form-control form-control-sm text-end" type="number" name="components[{{ $index }}][sort_order]" value="{{ $component['sort_order'] ?? $index + 1 }}"></td>
                    <td class="text-center"><input class="form-check-input" type="checkbox" name="components[{{ $index }}][is_active]" value="1" @checked($component['is_active'] ?? true)></td>
                    <td><button class="btn btn-sm btn-outline-danger" type="button" data-remove-component>Remove</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4 d-flex gap-2">
    <button class="btn btn-primary">Save Service</button>
    <a class="btn btn-outline-secondary" href="{{ route('services.index') }}">Cancel</a>
</div>

@push('scripts')
<script>
const componentsBody = document.querySelector('#componentsTable tbody');
let nextComponentIndex = componentsBody.querySelectorAll('tr').length;

function componentRow(index) {
    return `<tr>
        <td><input class="form-control form-control-sm" name="components[${index}][name]"></td>
        <td><textarea class="form-control form-control-sm" rows="2" name="components[${index}][description]"></textarea></td>
        <td><input class="form-control form-control-sm text-end" type="number" step="0.01" name="components[${index}][default_price]"></td>
        <td><input class="form-control form-control-sm text-end" type="number" name="components[${index}][sort_order]" value="${index + 1}"></td>
        <td class="text-center"><input class="form-check-input" type="checkbox" name="components[${index}][is_active]" value="1" checked></td>
        <td><button class="btn btn-sm btn-outline-danger" type="button" data-remove-component>Remove</button></td>
    </tr>`;
}

document.getElementById('addComponent').addEventListener('click', () => {
    componentsBody.insertAdjacentHTML('beforeend', componentRow(nextComponentIndex));
    nextComponentIndex++;
});

document.addEventListener('click', event => {
    if (event.target.matches('[data-remove-component]')) {
        event.target.closest('tr').remove();
    }
});
</script>
@endpush
