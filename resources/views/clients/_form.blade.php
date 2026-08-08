@csrf
@if (! $client->exists)
    <div class="panel p-3 bg-light mb-3">
        <div class="muted-label mb-2">Smart Paste New Client</div>
        <div class="row g-3">
            <div class="col-12">
                <textarea class="form-control" id="smartPasteText" placeholder="Paste client information copied from WhatsApp, email, or a message"></textarea>
            </div>
            <div class="col-12 d-flex gap-2 align-items-center">
                <button class="btn btn-sm btn-outline-primary" type="button" id="detectClient">Detect Information</button>
                <span class="text-secondary small" id="smartPasteStatus"></span>
            </div>
            <div class="col-12 d-none" id="duplicateWarning">
                <div class="alert alert-warning mb-0">
                    <strong>Possible existing client found.</strong>
                    <div class="mt-2" id="duplicateList"></div>
                </div>
            </div>
        </div>
    </div>
@endif
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Company Name</label><input class="form-control" name="company_name" value="{{ old('company_name', $client->company_name) }}" required></div>
    <div class="col-md-6"><label class="form-label">Parent/Group Company</label><input class="form-control" name="parent_company" value="{{ old('parent_company', $client->parent_company) }}"></div>
    <div class="col-md-4"><label class="form-label">Contact Person</label><input class="form-control" name="contact_person" value="{{ old('contact_person', $client->contact_person) }}"></div>
    <div class="col-md-4"><label class="form-label">Designation</label><input class="form-control" name="designation" value="{{ old('designation', $client->designation) }}"></div>
    <div class="col-md-4"><label class="form-label">Department</label><input class="form-control" name="department" value="{{ old('department', $client->department) }}"></div>
    <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $client->email) }}"></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ old('phone', $client->phone) }}"></div>
    <div class="col-md-4"><label class="form-label">Website</label><input class="form-control" name="website" value="{{ old('website', $client->website) }}"></div>
    <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" required>{{ old('address', $client->address) }}</textarea></div>
    <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" value="{{ old('city', $client->city) }}"></div>
    <div class="col-md-4"><label class="form-label">Postal Code</label><input class="form-control" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}"></div>
    <div class="col-md-4"><label class="form-label">Country</label><input class="form-control" name="country" value="{{ old('country', $client->country) }}"></div>
</div>
<div class="mt-4 d-flex gap-2">
    <button class="btn btn-primary">Save Client</button>
    <a class="btn btn-outline-secondary" href="{{ route('clients.index') }}">Cancel</a>
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
            populated++;
        }
    });
    return populated;
}

function showDuplicateWarning(duplicates) {
    const warning = document.getElementById('duplicateWarning');
    const list = document.getElementById('duplicateList');
    list.innerHTML = '';

    if (! duplicates?.length) {
        warning.classList.add('d-none');
        return;
    }

    duplicates.forEach(client => {
        const link = document.createElement('a');
        link.className = 'btn btn-sm btn-warning me-2 mb-2';
        link.href = `/clients/${client.id}`;
        link.textContent = `Open Existing Client: ${client.label}`;
        list.appendChild(link);
    });
    warning.classList.remove('d-none');
}

async function postJson(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: JSON.stringify(payload),
    });
    const json = await response.json().catch(() => ({}));
    if (! response.ok) throw { status: response.status, json };
    return json;
}

document.getElementById('detectClient').addEventListener('click', async () => {
    const status = document.getElementById('smartPasteStatus');
    const button = document.getElementById('detectClient');
    status.textContent = 'Detecting client information...';
    button.disabled = true;
    try {
        const result = await postJson('{{ route('clients.smart-paste') }}', { raw_text: document.getElementById('smartPasteText').value });
        const populated = fillClient(result.data || {});
        showDuplicateWarning(result.duplicates || []);
        status.textContent = populated
            ? (result.message || 'Information detected. Please review before saving.')
            : 'Information could not be detected automatically. Please enter the client details manually.';
    } catch (error) {
        const populated = error.json?.data ? fillClient(error.json.data) : 0;
        showDuplicateWarning(error.json?.duplicates || []);
        status.textContent = populated
            ? (error.json?.message || 'Some information was detected locally. Please review before saving.')
            : 'Information could not be detected automatically. Please enter the client details manually.';
    } finally {
        button.disabled = false;
    }
});
</script>
@endpush
@endif
