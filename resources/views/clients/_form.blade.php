@csrf
@if (! $client->exists)
    <div class="form-section" id="smart-paste" style="border-color:#d7cffb">
        <div class="fs-head" style="background:linear-gradient(90deg,#f2ecfd,#eaf4ef)">
            <span class="fs-ico" style="background:linear-gradient(135deg,#7c3aed,#2da46f);color:#fff"><x-icon name="sparkles" /></span>
            <div><div class="fs-t">Smart Paste</div><div class="fs-s">Paste messy client details from WhatsApp or email — we detect the fields for you.</div></div>
        </div>
        <div class="fs-body">
            <textarea class="form-control" id="smartPasteText" rows="4"
                placeholder="Paste client information from WhatsApp, email, a business card or any text format. AI will identify the available details automatically."></textarea>
            <div class="d-flex gap-2 align-items-center mt-2 flex-wrap">
                <button class="btn btn-outline-primary" type="button" id="detectClient"><x-icon name="sparkles" :size="16" /> Detect Information</button>
                <span class="badge-soft b-neutral d-none" id="smartPasteStatus"></span>
                <span class="badge-soft b-neutral d-none" id="smartPasteSource"></span>
            </div>
            <div class="d-none mt-2" id="smartPasteWarnings"></div>
            <div class="d-none mt-3" id="duplicateWarning">
                <div class="alert alert-warning mb-0">
                    <strong>Possible existing client found.</strong>
                    <div class="mt-2" id="duplicateList"></div>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="building" /></span><div><div class="fs-t">Company</div></div></div>
    <div class="fs-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Company Name</label><input class="form-control" name="company_name" value="{{ old('company_name', $client->company_name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Parent / Group Company</label><input class="form-control" name="parent_company" value="{{ old('parent_company', $client->parent_company) }}"></div>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="clients" /></span><div><div class="fs-t">Contact</div></div></div>
    <div class="fs-body">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Contact Person</label><input class="form-control" name="contact_person" value="{{ old('contact_person', $client->contact_person) }}"></div>
            <div class="col-md-4"><label class="form-label">Designation</label><input class="form-control" name="designation" value="{{ old('designation', $client->designation) }}"></div>
            <div class="col-md-4"><label class="form-label">Department</label><input class="form-control" name="department" value="{{ old('department', $client->department) }}"></div>
            <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $client->email) }}"></div>
            <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ old('phone', $client->phone) }}"></div>
            <div class="col-md-4"><label class="form-label">Website</label><input class="form-control" name="website" value="{{ old('website', $client->website) }}"></div>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="pin" /></span><div><div class="fs-t">Address</div></div></div>
    <div class="fs-body">
        <div class="row g-3">
            <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" required>{{ old('address', $client->address) }}</textarea></div>
            <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" value="{{ old('city', $client->city) }}"></div>
            <div class="col-md-4"><label class="form-label">Postal Code</label><input class="form-control" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}"></div>
            <div class="col-md-4"><label class="form-label">Country</label><input class="form-control" name="country" value="{{ old('country', $client->country) }}"></div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 justify-content-end">
    <a class="btn btn-outline-secondary" href="{{ route('clients.index') }}">Cancel</a>
    <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ $client->exists ? 'Update Client' : 'Save Client' }}</button>
</div>

@if (! $client->exists)
@push('scripts')
<script>
const clientFields = ['company_name', 'parent_company', 'contact_person', 'designation', 'department', 'email', 'phone', 'website', 'address', 'city', 'postal_code', 'country'];

function clientInput(field) {
    return document.querySelector(`[name="${field}"]`);
}

function fillClient(data) {
    let populated = 0;
    clientFields.forEach(field => {
        const input = clientInput(field);
        if (data[field] && input) {
            input.value = data[field];
            input.classList.add('is-valid');
            setTimeout(() => input.classList.remove('is-valid'), 1600);
            populated++;
        }
    });
    return populated;
}

function setStatus(text, kind) {
    const status = document.getElementById('smartPasteStatus');
    status.textContent = text;
    status.className = 'badge-soft ' + (kind || 'b-neutral');
}

// Honest provenance badge — never say "AI" when only local parsing ran.
function setSource(source, provider) {
    const el = document.getElementById('smartPasteSource');
    if (source === 'ai' && provider) {
        el.textContent = 'Detected by AI (' + provider + ')';
        el.className = 'badge-soft b-info';
        el.classList.remove('d-none');
    } else if (source === 'local') {
        el.textContent = 'Detected locally (AI unavailable)';
        el.className = 'badge-soft b-warn';
        el.classList.remove('d-none');
    } else {
        el.classList.add('d-none');
    }
}

function showWarnings(warnings) {
    const box = document.getElementById('smartPasteWarnings');
    if (! warnings || ! warnings.length) { box.classList.add('d-none'); box.innerHTML = ''; return; }
    box.innerHTML = '<div class="alert alert-warning mb-0 py-2 small">' +
        warnings.map(w => '• ' + w.replace(/</g, '&lt;')).join('<br>') + '</div>';
    box.classList.remove('d-none');
}

function showDuplicateWarning(duplicates) {
    const warning = document.getElementById('duplicateWarning');
    const list = document.getElementById('duplicateList');
    list.innerHTML = '';
    if (! duplicates?.length) { warning.classList.add('d-none'); return; }
    duplicates.forEach(client => {
        const link = document.createElement('a');
        link.className = 'btn btn-sm btn-warning me-2 mb-2';
        link.href = `/clients/${client.id}`;
        link.textContent = `Open existing: ${client.label}`;
        list.appendChild(link);
    });
    warning.classList.remove('d-none');
}

async function postJson(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(payload),
    });
    const json = await response.json().catch(() => ({}));
    if (! response.ok) throw { status: response.status, json };
    return json;
}

document.getElementById('detectClient').addEventListener('click', async () => {
    const button = document.getElementById('detectClient');
    setStatus('Detecting…', 'b-info');
    button.classList.add('is-loading');
    button.disabled = true;
    try {
        const result = await postJson('{{ route('clients.smart-paste') }}', { raw_text: document.getElementById('smartPasteText').value });
        const populated = fillClient(result.data || {});
        setSource(result.source, result.provider);
        showWarnings(result.warnings || []);
        showDuplicateWarning(result.duplicates || []);
        setStatus(populated ? (result.message || 'Detected — please review') : 'Nothing detected — enter manually', populated ? 'b-ok' : 'b-warn');
    } catch (error) {
        const populated = error.json?.data ? fillClient(error.json.data) : 0;
        setSource(error.json?.source, error.json?.provider);
        showWarnings(error.json?.warnings || []);
        showDuplicateWarning(error.json?.duplicates || []);
        if (error.status === 422) {
            setStatus(error.json?.message || 'Could not detect — enter manually', 'b-warn');
        } else if (error.status === 419 || error.status === 0 || error.status === undefined) {
            setStatus('Network error — your pasted text is preserved. Please try again.', 'b-danger');
        } else {
            setStatus(error.json?.message || 'Something went wrong — enter manually', populated ? 'b-warn' : 'b-danger');
        }
    } finally {
        button.classList.remove('is-loading');
        button.disabled = false;
    }
});
</script>
@endpush
@endif
