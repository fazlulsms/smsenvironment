@csrf
@if ($standard->exists)@method('put')@endif

<div class="card"><div class="card-body">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Name <span class="text-danger">*</span></label>
            <input class="form-control" name="name" value="{{ old('name', $standard->name) }}" required placeholder="e.g. ISO 9001 — Quality Management Systems">
            @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Short Name / Code</label>
            <input class="form-control" name="short_name" value="{{ old('short_name', $standard->short_name) }}" placeholder="e.g. ISO 9001">
        </div>

        <div class="col-md-4">
            <label class="form-label">Category <span class="text-danger">*</span></label>
            <select class="form-select" name="service_category_id" required>
                <option value="">— select —</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('service_category_id', $standard->service_category_id) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            @error('service_category_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Standard Code <span class="text-secondary fw-normal">(optional)</span></label>
            <input class="form-control" name="code" value="{{ old('code', $standard->code) }}" placeholder="e.g. ISO 9001, GOTS">
        </div>
        <div class="col-md-4">
            <label class="form-label">Type <span class="text-secondary fw-normal">(metadata)</span></label>
            <input class="form-control" name="type" value="{{ old('type', $standard->type) }}" placeholder="e.g. ISO Standard, Certification Scheme, Environmental Service">
        </div>

        <div class="col-12">
            <label class="form-label">Description <span class="text-secondary fw-normal">(optional)</span></label>
            <textarea class="form-control" name="description" rows="2">{{ old('description', $standard->description) }}</textarea>
        </div>

        <div class="col-12">
            <label class="form-label">Package Components / Default Scope</label>
            <textarea class="form-control" name="default_scope" rows="5" placeholder="One included item per line — only for packages/bundles.&#10;e.g. Ambient Air Quality Assessment&#10;Stack Emission Test">{{ old('default_scope', $standard->default_scope) }}</textarea>
            <div class="form-hint mt-1">Leave blank for a plain standard/service. When present, selecting this item attaches these items as its scope on documents.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label">Display Order</label>
            <input class="form-control" type="number" min="0" name="display_order" value="{{ old('display_order', $standard->display_order ?? 0) }}">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <input type="hidden" name="active" value="0">
            <label class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" @checked(old('active', $standard->active ?? true))> <span class="form-check-label">Active</span></label>
        </div>
    </div>
</div></div>

<div class="d-flex gap-2 justify-content-end mt-3">
    <a class="btn btn-outline-secondary" href="{{ route('services.index') }}">Cancel</a>
    <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ $standard->exists ? 'Save Changes' : 'Create Catalogue Item' }}</button>
</div>
