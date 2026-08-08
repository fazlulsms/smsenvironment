@csrf
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Service Name</label><input class="form-control" name="name" value="{{ old('name', $service->name) }}" required></div>
    <div class="col-md-3"><label class="form-label">Short / Display Name</label><input class="form-control" name="short_name" value="{{ old('short_name', $service->short_name) }}"></div>
    <div class="col-md-3"><label class="form-label">Category</label><input class="form-control" name="category" value="{{ old('category', $service->category) }}"></div>
    <div class="col-12"><label class="form-label">Default Description</label><textarea class="form-control" name="default_description">{{ old('default_description', $service->default_description) }}</textarea></div>
    <div class="col-md-6"><label class="form-label">Default Unit</label><input class="form-control" name="default_unit" value="{{ old('default_unit', $service->default_unit) }}"></div>
    <div class="col-md-6"><label class="form-label">Default Rate</label><input class="form-control" type="number" step="0.01" name="default_rate" value="{{ old('default_rate', $service->default_rate ?? 0) }}" required></div>
    <div class="col-12"><label class="form-label">Quotation Subject Template</label><input class="form-control" name="quotation_subject_template" value="{{ old('quotation_subject_template', $service->quotation_subject_template) }}"></div>
    <div class="col-12"><label class="form-label">Quotation Scope / Description</label><textarea class="form-control" name="quotation_scope">{{ old('quotation_scope', $service->quotation_scope) }}</textarea></div>
    <div class="col-12"><label class="form-label">Applicable Standards / Compliance Note</label><textarea class="form-control" name="compliance_note">{{ old('compliance_note', $service->compliance_note) }}</textarea></div>
    <div class="col-12"><label class="form-label">Invoice Description / Charge For</label><textarea class="form-control" name="invoice_description">{{ old('invoice_description', $service->invoice_description) }}</textarea></div>
    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active ?? true))> <span class="form-check-label">Active</span></label></div>
</div>
<div class="mt-4 d-flex gap-2">
    <button class="btn btn-primary">Save Service</button>
    <a class="btn btn-outline-secondary" href="{{ route('services.index') }}">Cancel</a>
</div>
