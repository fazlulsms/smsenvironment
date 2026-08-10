@php
    $isQuotation = $type === 'quotation';
    $document = $isQuotation ? $quotation : $invoice;
    $items = old('items') ?: ($document->items?->map(fn ($item) => [
        'service_id' => $item->service_id,
        'pricing_mode' => $item->pricing_mode,
        'description' => $item->description,
        'scope_items' => implode("\n", $item->scope_items ?? []),
        'unit' => $item->unit,
        'quantity' => $item->quantity,
        'unit_rate' => $item->unit_rate,
    ])->toArray() ?: [
        ['service_id' => '', 'pricing_mode' => 'separate', 'description' => '', 'scope_items' => '', 'unit' => 'Job', 'quantity' => 1, 'unit_rate' => 0],
    ]);
    $showQuickClient = old('new_client.company_name') || ! old('client_id', request('client_id', $document->client_id));
    $defaultBank = $bankAccounts->firstWhere('is_default', true);
    $selectedBankId = old('bank_account_id', $document->bank_account_id ?: ($defaultBank?->id ?: ($bankAccounts->count() === 1 ? $bankAccounts->first()->id : null)));
    $showAdvanced = $errors->has('subject') || $errors->has('charge_for') || $errors->has('intro_text') || $errors->has('payment_terms') || $errors->has('notes') || $errors->has('vat_treatment') || $errors->has('vat_rate');
    $currency = $settings->default_currency ?: 'BDT';
@endphp

@csrf
@if ($document->exists)
    @method('put')
@endif

{{-- 1 · Client & document --}}
<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="clients" /></span><div><div class="fs-t">Client &amp; Document</div><div class="fs-s">Select an existing client or add a new one.</div></div></div>
    <div class="fs-body">
        <div class="row g-3 align-items-end">
            <div class="col-lg-6">
                <label class="form-label">Select Existing Client</label>
                <input class="form-control mb-2" id="clientSearch" placeholder="Type to filter clients">
                <select class="form-select" name="client_id" id="clientSelect">
                    <option value="">Use quick client below</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id', request('client_id', $document->client_id)) == $client->id)>
                            {{ $client->company_name }}{{ $client->contact_person ? ' - '.$client->contact_person : '' }}{{ $client->email ? ' - '.$client->email : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <label class="form-label">Date</label>
                <input class="form-control" type="date" name="date" value="{{ old('date', optional($document->date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required>
            </div>
            <div class="col-lg-4">
                <label class="form-label">Bank Account</label>
                <select class="form-select" name="bank_account_id">
                    <option value="">Select bank before PDF</option>
                    @foreach ($bankAccounts as $bankAccount)
                        <option value="{{ $bankAccount->id }}" @selected($selectedBankId == $bankAccount->id)>
                            {{ $bankAccount->bank_name }} - {{ $bankAccount->account_number }}{{ $bankAccount->is_default ? ' (Default)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <span class="badge-soft b-neutral">{{ $isQuotation ? 'Quotation' : 'Invoice' }} No. {{ $document->number }}</span>
            </div>
            <div class="col-12">
                <button class="btn btn-outline-primary btn-sm" type="button" id="toggleQuickClient" aria-expanded="{{ $showQuickClient ? 'true' : 'false' }}">
                    <x-icon name="plus" :size="15" /> Add New Client
                </button>
                <div class="collapse mt-2 {{ $showQuickClient ? 'show' : '' }}" id="quickClient">
                    <div class="p-3 rounded" style="background:#faf9ff;border:1px solid #ece7fb">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge-soft b-service"><x-icon name="sparkles" :size="12" /> Smart Paste</span>
                            <span class="text-secondary small">Paste from WhatsApp or email, then detect.</span>
                        </div>
                        <textarea class="form-control mb-2" id="smartPasteText" rows="3" placeholder="Paste client information copied from WhatsApp, email, or a message"></textarea>
                        <div class="d-flex gap-2 align-items-center flex-wrap mb-3">
                            <button class="btn btn-sm btn-outline-primary" type="button" id="detectClient"><x-icon name="sparkles" :size="15" /> Detect Information</button>
                            <button class="btn btn-sm btn-primary" type="button" id="saveQuickClient"><x-icon name="check" :size="15" /> Save Client</button>
                            <span class="badge-soft b-neutral d-none" id="smartPasteStatus"></span>
                        </div>
                        <div class="col-12 d-none mb-2" id="duplicateWarning">
                            <div class="alert alert-warning mb-0">
                                <strong>Possible existing client found.</strong>
                                <div class="mt-2" id="duplicateList"></div>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6"><input class="form-control" name="new_client[company_name]" value="{{ old('new_client.company_name') }}" placeholder="Company name"></div>
                            <div class="col-md-6"><input class="form-control" name="new_client[parent_company]" value="{{ old('new_client.parent_company') }}" placeholder="Parent / group company"></div>
                            <div class="col-md-4"><input class="form-control" name="new_client[contact_person]" value="{{ old('new_client.contact_person') }}" placeholder="Contact person"></div>
                            <div class="col-md-4"><input class="form-control" name="new_client[designation]" value="{{ old('new_client.designation') }}" placeholder="Designation"></div>
                            <div class="col-md-4"><input class="form-control" name="new_client[department]" value="{{ old('new_client.department') }}" placeholder="Department"></div>
                            <div class="col-md-4"><input class="form-control" type="email" name="new_client[email]" value="{{ old('new_client.email') }}" placeholder="Email"></div>
                            <div class="col-md-4"><input class="form-control" name="new_client[phone]" value="{{ old('new_client.phone') }}" placeholder="Phone"></div>
                            <div class="col-md-4"><input class="form-control" name="new_client[website]" value="{{ old('new_client.website') }}" placeholder="Website"></div>
                            <div class="col-12"><textarea class="form-control" name="new_client[address]" placeholder="Address">{{ old('new_client.address') }}</textarea></div>
                            <div class="col-md-4"><input class="form-control" name="new_client[city]" value="{{ old('new_client.city') }}" placeholder="City"></div>
                            <div class="col-md-4"><input class="form-control" name="new_client[postal_code]" value="{{ old('new_client.postal_code') }}" placeholder="Postal code"></div>
                            <div class="col-md-4"><input class="form-control" name="new_client[country]" value="{{ old('new_client.country') }}" placeholder="Country"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 2 · Services & scope --}}
<div class="form-section">
    <div class="fs-head">
        <span class="fs-ico"><x-icon name="services" /></span>
        <div><div class="fs-t">Services &amp; Scope</div><div class="fs-s">Pick a service to auto-fill its description, scope and rate.</div></div>
        <button class="btn btn-outline-primary btn-sm ms-auto" type="button" id="addItem"><x-icon name="plus" :size="15" /> Add Service Line</button>
    </div>
    <div class="fs-body">
        <div class="table-responsive">
            <table class="table align-middle" id="itemsTable">
                <thead><tr><th style="width:20%">Service</th><th>Description / Scope</th><th style="width:9%">Unit</th><th style="width:9%">Qty</th><th style="width:14%" class="num">Rate</th><th style="width:14%" class="num">Amount</th><th></th></tr></thead>
                <tbody>
                @foreach ($items as $index => $item)
                    @include('documents.item_row', ['index' => $index, 'item' => $item])
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="row justify-content-end">
            <div class="col-md-5 col-lg-4">
                <div class="d-flex justify-content-between py-1"><span class="text-secondary">Subtotal</span><strong class="num" id="subtotal">0.00</strong></div>
                <label class="form-label mt-2">Adjustment</label>
                <input class="form-control num calc" type="number" step="0.01" name="adjustment" value="{{ old('adjustment', $document->adjustment ?? 0) }}">
                <div class="d-flex justify-content-between align-items-center py-2 mt-2 px-2 rounded" style="background:var(--brand-050)">
                    <span class="fw-semibold">Total</span>
                    <strong class="num" style="font-size:18px"><span class="cur">{{ $currency }}</span> <span id="grandTotal">0.00</span></strong>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 3 · Advanced document content --}}
<details class="adv" {{ $showAdvanced ? 'open' : '' }} id="advancedWrap">
    <summary>
        <x-icon name="settings" :size="16" /> Advanced &amp; Tax / VAT
        <span class="text-secondary fw-normal ms-2" style="font-size:12px">Wording overrides &amp; tax treatment — optional</span>
        <span class="chev"><x-icon name="chevron-left" :size="14" style="transform:rotate(-90deg)" /></span>
    </summary>
    <div class="adv-body">
        {{-- Hidden mirror kept so existing JS that toggles #advancedDocument still functions --}}
        <div id="advancedDocument" class="show">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">{{ $isQuotation ? 'Subject Override' : 'Charge For Override' }}</label>
                    @if ($isQuotation)
                        <input class="form-control" name="subject" value="{{ old('subject', $document->subject) }}" placeholder="Leave blank to auto-generate">
                    @else
                        <input class="form-control" name="charge_for" value="{{ old('charge_for', $document->charge_for) }}" placeholder="Leave blank to auto-generate">
                    @endif
                </div>
                @if ($isQuotation)
                    <div class="col-12"><label class="form-label">Introduction Override</label><textarea class="form-control" name="intro_text" placeholder="Leave blank to use template">{{ old('intro_text', $document->intro_text) }}</textarea></div>
                    <div class="col-12"><label class="form-label">Compliance / Standards Note Override</label><textarea class="form-control" name="compliance_note" placeholder="Leave blank to use service notes">{{ old('compliance_note', $document->compliance_note) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Scope of Assessment Override</label><textarea class="form-control" name="scope_assessment" placeholder="Leave blank to use generated scope">{{ old('scope_assessment', $document->scope_assessment) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Methodology Override</label><textarea class="form-control" name="methodology" placeholder="Leave blank to use generated methodology">{{ old('methodology', $document->methodology) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Deliverables Override</label><textarea class="form-control" name="deliverables" placeholder="Leave blank to use generated deliverables">{{ old('deliverables', $document->deliverables) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Client Responsibilities Override</label><textarea class="form-control" name="client_responsibilities" placeholder="Leave blank to use generated responsibilities">{{ old('client_responsibilities', $document->client_responsibilities) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Closing Text Override</label><textarea class="form-control" name="closing_text">{{ old('closing_text', $document->closing_text) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Validity Text Override</label><textarea class="form-control" name="validity_text">{{ old('validity_text', $document->validity_text) }}</textarea></div>
                @else
                    <div class="col-12"><label class="form-label">Validity Text Override</label><textarea class="form-control" name="validity_text">{{ old('validity_text', $document->validity_text) }}</textarea></div>
                @endif
                <div class="col-md-8"><label class="form-label">Payment Terms Override</label><textarea class="form-control" name="payment_terms" placeholder="Leave blank to use defaults">{{ old('payment_terms', $document->payment_terms) }}</textarea></div>
                <div class="col-md-4"><label class="form-label">Notes Override</label><textarea class="form-control" name="notes">{{ old('notes', $document->notes) }}</textarea></div>
                <div class="col-md-4">
                    <label class="form-label">VAT Treatment</label>
                    <select class="form-select" name="vat_treatment">
                        @foreach (['exclusive' => 'Exclusive of VAT', 'included' => 'VAT Included', 'add' => 'Add VAT', 'not_applicable' => 'Not Applicable'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('vat_treatment', $document->vat_treatment ?: ($settings->quotation_vat_treatment ?? 'exclusive')) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">VAT Rate (%)</label><input class="form-control" type="number" step="0.001" name="vat_rate" value="{{ old('vat_rate', $document->vat_rate ?: $settings->quotation_vat_rate) }}"></div>
                <div class="col-md-4 d-flex align-items-end"><input type="hidden" name="show_vat_separately" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="show_vat_separately" value="1" @checked(old('show_vat_separately', $document->show_vat_separately ?? ($settings->quotation_show_vat_separately ?? true)))> <span class="form-check-label">Show VAT separately</span></label></div>
                @if ($isQuotation)
                    <div class="col-md-6"><label class="form-label">VAT Note Override</label><textarea class="form-control" name="vat_note">{{ old('vat_note', $document->vat_note) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">AIT / Tax Note Override</label><textarea class="form-control" name="ait_note">{{ old('ait_note', $document->ait_note) }}</textarea></div>
                    <div class="col-12"><label class="form-label">Terms &amp; Conditions Override</label><textarea class="form-control" rows="5" name="terms_conditions">{{ old('terms_conditions', $document->terms_conditions) }}</textarea></div>
                    <div class="col-md-4 d-flex align-items-end"><input type="hidden" name="include_acceptance" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="include_acceptance" value="1" @checked(old('include_acceptance', $document->include_acceptance ?? ($settings->quotation_include_acceptance ?? true)))> <span class="form-check-label">Include Acceptance</span></label></div>
                    <div class="col-md-8"><label class="form-label">Acceptance Wording Override</label><textarea class="form-control" name="acceptance_text">{{ old('acceptance_text', $document->acceptance_text) }}</textarea></div>
                @endif
            </div>
        </div>
    </div>
</details>

<div class="d-flex gap-2 justify-content-end mt-3">
    <a class="btn btn-outline-secondary" href="{{ $isQuotation ? route('quotations.index') : route('proforma-invoices.index') }}">Cancel</a>
    <button class="btn btn-outline-primary" type="submit" name="after_save" value="pdf" data-no-loading><x-icon name="download" :size="16" /> Save &amp; Download PDF</button>
    <button class="btn btn-primary" type="submit" name="after_save" value="view" data-no-loading><x-icon name="eye" :size="16" /> Save &amp; Preview</button>
</div>

@push('scripts')
<script>
const services = @json($services->keyBy('id'));
const tableBody = document.querySelector('#itemsTable tbody');
const clientSearch = document.getElementById('clientSearch');
const clientSelect = document.getElementById('clientSelect');
const quickClient = document.getElementById('quickClient');
const toggleQuickClient = document.getElementById('toggleQuickClient');
const advancedDocument = document.getElementById('advancedDocument');
const allClientOptions = Array.from(clientSelect.options).map(option => option.cloneNode(true));
let nextItemIndex = tableBody.querySelectorAll('tr').length;
const clientFields = ['company_name', 'parent_company', 'contact_person', 'designation', 'department', 'email', 'phone', 'website', 'address', 'city', 'postal_code', 'country'];
let duplicateClients = [];
let pendingDuplicatePayload = null;

function rowTemplate(index) {
    return `@include('documents.item_row_template')`.replaceAll('__INDEX__', index);
}

function recalc() {
    let subtotal = 0;
    tableBody.querySelectorAll('tr').forEach(row => {
        const qty = parseFloat(row.querySelector('[data-qty]').value || 0);
        const rate = parseFloat(row.querySelector('[data-rate]').value || 0);
        const amount = qty * rate;
        subtotal += amount;
        row.querySelector('[data-amount]').textContent = amount.toFixed(2);
    });
    const adjustment = parseFloat(document.querySelector('[name="adjustment"]').value || 0);
    const vatTreatment = document.querySelector('[name="vat_treatment"]')?.value || 'exclusive';
    const vatRate = parseFloat(document.querySelector('[name="vat_rate"]')?.value || 0);
    const net = subtotal + adjustment;
    const vat = vatTreatment === 'add' ? net * vatRate / 100 : 0;
    document.getElementById('subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('grandTotal').textContent = (net + vat).toFixed(2);
}

document.getElementById('addItem').addEventListener('click', () => {
    tableBody.insertAdjacentHTML('beforeend', rowTemplate(nextItemIndex));
    nextItemIndex++;
});

clientSearch.addEventListener('input', () => {
    const term = clientSearch.value.toLowerCase();
    clientSelect.replaceChildren(...allClientOptions
        .filter(option => !option.value || option.text.toLowerCase().includes(term))
        .map(option => option.cloneNode(true)));
});

clientSelect.addEventListener('change', () => {
    if (!clientSelect.value) return;
    quickClient.classList.remove('show');
    toggleQuickClient.setAttribute('aria-expanded', 'false');
});

function clientInput(field) {
    return document.querySelector(`[name="new_client[${field}]"]`);
}

function quickClientData() {
    const data = {};
    clientFields.forEach(field => data[field] = clientInput(field)?.value || '');
    return data;
}

function fillQuickClient(data) {
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

function setQuickStatus(text, kind) {
    const status = document.getElementById('smartPasteStatus');
    status.textContent = text;
    status.className = 'badge-soft ' + (kind || 'b-neutral');
}

function showDuplicateWarning(duplicates) {
    duplicateClients = duplicates || [];
    const warning = document.getElementById('duplicateWarning');
    const list = document.getElementById('duplicateList');
    list.innerHTML = '';

    if (! duplicateClients.length) {
        warning.classList.add('d-none');
        return;
    }

    duplicateClients.forEach(client => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-warning me-2 mb-2';
        button.textContent = `Use Existing Client: ${client.label}`;
        button.addEventListener('click', () => selectClientOption(client));
        list.appendChild(button);
    });

    if (pendingDuplicatePayload) {
        const createButton = document.createElement('button');
        createButton.type = 'button';
        createButton.className = 'btn btn-sm btn-outline-warning mb-2';
        createButton.textContent = 'Create New Client Anyway';
        createButton.addEventListener('click', () => saveQuickClient({ ...pendingDuplicatePayload, create_anyway: true }));
        list.appendChild(createButton);
    }

    warning.classList.remove('d-none');
}

function selectClientOption(client) {
    let option = Array.from(clientSelect.options).find(option => option.value == client.id);
    if (! option) {
        option = new Option(client.label, client.id, true, true);
        clientSelect.add(option);
        allClientOptions.push(option.cloneNode(true));
    }
    clientSelect.value = client.id;
    quickClient.classList.remove('show');
    toggleQuickClient.setAttribute('aria-expanded', 'false');
    showDuplicateWarning([]);
}

async function saveQuickClient(payload) {
    setQuickStatus('Saving…', 'b-info');
    const result = await postJson('{{ route('clients.smart-store') }}', payload);
    pendingDuplicatePayload = null;
    selectClientOption(result.client);
    setQuickStatus('Client saved and selected', 'b-ok');
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
    const button = document.getElementById('detectClient');
    setQuickStatus('Detecting…', 'b-info');
    button.classList.add('is-loading');
    button.disabled = true;
    try {
        const result = await postJson('{{ route('clients.smart-paste') }}', { raw_text: document.getElementById('smartPasteText').value });
        const populated = fillQuickClient(result.data || {});
        showDuplicateWarning(result.duplicates || []);
        setQuickStatus(populated ? (result.message || 'Detected — please review') : 'Nothing detected — enter manually', populated ? 'b-ok' : 'b-warn');
    } catch (error) {
        const populated = error.json?.data ? fillQuickClient(error.json.data) : 0;
        showDuplicateWarning(error.json?.duplicates || []);
        setQuickStatus(populated ? (error.json?.message || 'Detected locally — review') : 'Could not detect — enter manually', populated ? 'b-warn' : 'b-danger');
    } finally {
        button.classList.remove('is-loading');
        button.disabled = false;
    }
});

document.getElementById('saveQuickClient').addEventListener('click', async () => {
    const button = document.getElementById('saveQuickClient');
    pendingDuplicatePayload = quickClientData();
    button.classList.add('is-loading');
    button.disabled = true;
    try {
        await saveQuickClient(pendingDuplicatePayload);
    } catch (error) {
        if (error.status === 409) {
            showDuplicateWarning(error.json?.duplicates || []);
            setQuickStatus('Possible existing client — choose or edit', 'b-warn');
            return;
        }
        setQuickStatus(error.json?.message || 'Could not save — check required fields', 'b-danger');
    } finally {
        button.classList.remove('is-loading');
        button.disabled = false;
    }
});

toggleQuickClient.addEventListener('click', () => {
    quickClient.classList.toggle('show');
    toggleQuickClient.setAttribute('aria-expanded', quickClient.classList.contains('show') ? 'true' : 'false');
});

document.addEventListener('input', event => {
    if (event.target.matches('.calc, [name="vat_rate"]')) recalc();
});

document.addEventListener('change', event => {
    if (event.target.matches('[name="vat_treatment"], [name="show_vat_separately"]')) {
        recalc();
        return;
    }

    if (! event.target.matches('[data-service-select]')) return;
    const service = services[event.target.value];
    if (! service) return;
    const row = event.target.closest('tr');
    row.querySelector('[data-description]').value = {{ $isQuotation ? 'service.quotation_scope || service.default_description || service.name' : 'service.invoice_description || service.default_description || service.name' }};
    row.querySelector('[data-pricing-mode]').value = service.service_type === 'standalone' ? 'separate' : 'consolidated';
    row.querySelector('[data-scope-items]').value = (service.components || [])
        .filter(component => component.is_active)
        .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
        .map(component => component.name)
        .join("\n");
    row.querySelector('[data-unit]').value = service.default_unit || '';
    row.querySelector('[data-rate]').value = service.default_rate || 0;
    recalc();
});

document.addEventListener('click', event => {
    if (! event.target.matches('[data-remove-row]')) return;
    if (tableBody.querySelectorAll('tr').length > 1) event.target.closest('tr').remove();
    recalc();
});

recalc();
</script>
@endpush
