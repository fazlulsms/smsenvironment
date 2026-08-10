@csrf
<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="bank" /></span><div><div class="fs-t">Bank Account</div><div class="fs-s">Only the selected account appears on each document.</div></div></div>
    <div class="fs-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Beneficiary Name</label><input class="form-control" name="beneficiary_name" value="{{ old('beneficiary_name', $bankAccount->beneficiary_name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Bank Name</label><input class="form-control" name="bank_name" value="{{ old('bank_name', $bankAccount->bank_name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Branch</label><input class="form-control" name="branch" value="{{ old('branch', $bankAccount->branch) }}"></div>
            <div class="col-md-6"><label class="form-label">Account Number</label><input class="form-control" name="account_number" value="{{ old('account_number', $bankAccount->account_number) }}" required></div>
            <div class="col-md-6"><label class="form-label">Routing Number</label><input class="form-control" name="routing_number" value="{{ old('routing_number', $bankAccount->routing_number) }}"></div>
            <div class="col-md-6"><label class="form-label">SWIFT Code</label><input class="form-control" name="swift_code" value="{{ old('swift_code', $bankAccount->swift_code) }}"></div>
            <div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $bankAccount->is_active ?? true))> <span class="form-check-label">Active</span></label></div>
            <div class="col-md-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_default" value="1" @checked(old('is_default', $bankAccount->is_default ?? false))> <span class="form-check-label">Default for documents</span></label></div>
        </div>
    </div>
</div>
<div class="d-flex gap-2 justify-content-end">
    <a class="btn btn-outline-secondary" href="{{ route('bank-accounts.index') }}">Cancel</a>
    <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ $bankAccount->exists ? 'Update' : 'Save' }} Bank Account</button>
</div>
