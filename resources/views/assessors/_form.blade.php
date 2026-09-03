@csrf
<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="clients" /></span><div><div class="fs-t">Assessor</div><div class="fs-s">Contact details for assignment emails.</div></div></div>
    <div class="fs-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $assessor->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Designation / Role</label><input class="form-control" name="designation" value="{{ old('designation', $assessor->designation) }}"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $assessor->email) }}"></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ old('phone', $assessor->phone) }}"></div>
            <div class="col-12"><label class="form-label">Note</label><input class="form-control" name="note" value="{{ old('note', $assessor->note) }}"></div>
            <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $assessor->is_active ?? true))> <span class="form-check-label">Active</span></label></div>
        </div>
    </div>
</div>
<div class="d-flex gap-2 justify-content-end">
    <a class="btn btn-outline-secondary" href="{{ route('assessors.index') }}">Cancel</a>
    <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ $assessor->exists ? 'Update' : 'Save' }} Assessor</button>
</div>
