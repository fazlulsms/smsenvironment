@extends('layouts.app', ['title' => 'Edit '.$entity->name])

@php $theme = $entity->theme(); @endphp

@section('content')
<x-page-toolbar title="Edit Entity" subtitle="{{ $entity->name }} · {{ $entity->entity_code }}">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('entities.index') }}"><x-icon name="chevron-left" :size="15" /> All entities</a>
</x-page-toolbar>

<form method="post" action="{{ route('entities.update', $entity) }}" enctype="multipart/form-data" data-loading>
    @csrf @method('put')

    <div class="form-section">
        <div class="fs-head"><span class="fs-ico"><x-icon name="building" /></span><div><div class="fs-t">Identity</div><div class="fs-s">Shown in the switcher, dashboard and (via document profile) on PDFs.</div></div></div>
        <div class="fs-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $entity->name) }}" required></div>
                <div class="col-md-3"><label class="form-label">Short Name</label><input class="form-control" name="short_name" value="{{ old('short_name', $entity->short_name) }}"></div>
                <div class="col-md-3"><label class="form-label">Entity Code</label><input class="form-control" value="{{ $entity->entity_code }}" disabled><div class="form-hint">Locked — anchors QR verification.</div></div>
                <div class="col-md-6"><label class="form-label">Legal Name</label><input class="form-control" name="legal_name" value="{{ old('legal_name', $entity->legal_name) }}"></div>
                <div class="col-md-6"><label class="form-label">Tagline</label><input class="form-control" name="tagline" value="{{ old('tagline', $entity->tagline) }}"></div>
                <div class="col-md-8"><label class="form-label">Address</label><input class="form-control" name="address" value="{{ old('address', $entity->address) }}"></div>
                <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" value="{{ old('city', $entity->city) }}"></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ old('phone', $entity->phone) }}"></div>
                <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $entity->email) }}"></div>
                <div class="col-md-4"><label class="form-label">Website</label><input class="form-control" name="website" value="{{ old('website', $entity->website) }}"></div>
                <div class="col-md-4"><label class="form-label">Finance Email</label><input class="form-control" type="email" name="finance_email" value="{{ old('finance_email', $entity->finance_email) }}"></div>
                <div class="col-md-4"><label class="form-label">Secondary Phone</label><input class="form-control" name="secondary_phone" value="{{ old('secondary_phone', $entity->secondary_phone) }}"></div>
                <div class="col-md-4"><label class="form-label">Default Currency</label><input class="form-control" name="default_currency" value="{{ old('default_currency', $entity->default_currency) }}" required></div>
            </div>
        </div>
    </div>

    <div class="form-section">
        <div class="fs-head"><span class="fs-ico"><x-icon name="sparkles" /></span><div><div class="fs-t">Theme &amp; Logo</div><div class="fs-s">Application colours for this workspace. PDF branding is separate.</div></div></div>
        <div class="fs-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3"><label class="form-label">Primary</label><input class="form-control form-control-color" type="color" name="primary_color" value="{{ old('primary_color', $theme['primary']) }}"></div>
                <div class="col-md-3"><label class="form-label">Secondary</label><input class="form-control form-control-color" type="color" name="secondary_color" value="{{ old('secondary_color', $theme['secondary']) }}"></div>
                <div class="col-md-3"><label class="form-label">Accent</label><input class="form-control form-control-color" type="color" name="accent_color" value="{{ old('accent_color', $theme['accent']) }}"></div>
                <div class="col-md-3">
                    <div class="d-flex gap-1">
                        <span style="flex:1;height:34px;border-radius:8px;background:{{ $theme['primary'] }}"></span>
                        <span style="flex:1;height:34px;border-radius:8px;background:{{ $theme['secondary'] }}"></span>
                        <span style="flex:1;height:34px;border-radius:8px;background:{{ $theme['accent'] }}"></span>
                    </div>
                </div>
                <div class="col-md-6"><label class="form-label">Logo</label><input class="form-control" type="file" name="logo" accept="image/*">
                    @if ($entity->logo_path)<div class="form-hint">Current logo saved. Upload to replace (aspect ratio preserved).</div>@endif</div>
                @if ($entity->logoUrl())
                    <div class="col-md-6"><img src="{{ $entity->logoUrl() }}" alt="logo" style="max-height:52px;max-width:180px;object-fit:contain"></div>
                @endif
            </div>
        </div>
    </div>

    <div class="form-section">
        <div class="fs-head"><span class="fs-ico"><x-icon name="settings" /></span><div><div class="fs-t">Availability &amp; Features</div></div></div>
        <div class="fs-body">
            <div class="d-flex flex-wrap gap-3">
                <label class="form-check"><input class="form-check-input" type="checkbox" name="active" value="1" @checked(old('active', $entity->active))> <span class="form-check-label">Active</span></label>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="quotation_enabled" value="1" @checked(old('quotation_enabled', $entity->quotation_enabled))> <span class="form-check-label">Quotations</span></label>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="proforma_invoice_enabled" value="1" @checked(old('proforma_invoice_enabled', $entity->proforma_invoice_enabled))> <span class="form-check-label">Proforma Invoices</span></label>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled', $entity->email_enabled))> <span class="form-check-label">Email</span></label>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="qr_verification_enabled" value="1" @checked(old('qr_verification_enabled', $entity->qr_verification_enabled))> <span class="form-check-label">QR Verification</span></label>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-end">
        <a class="btn btn-outline-secondary" href="{{ route('entities.index') }}">Cancel</a>
        <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Save Entity</button>
    </div>
</form>
@endsection
