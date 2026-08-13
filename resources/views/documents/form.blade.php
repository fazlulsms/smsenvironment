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

{{-- 1b · Service category & standards (global master) --}}
@isset($serviceCategories)
    @php
        $selectedStandardIds = collect(old('standards', collect($document->standards_snapshot['items'] ?? [])->pluck('standard_id')))
            ->filter()->map(fn ($i) => (int) $i)->values();
        $categoriesJson = $serviceCategories->map(fn ($c) => [
            'id' => $c->id,
            'label' => $c->selectionLabel(),
            'standards' => $c->activeStandards->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'code' => $s->shortLabel()])->values(),
        ])->values();
    @endphp
    <div class="form-section" id="standardsSection">
        <div class="fs-head">
            <span class="fs-ico"><x-icon name="services" /></span>
            <div><div class="fs-t">Service &amp; Standards</div><div class="fs-s">Pick a service category, then search and select one or more standards / programs. The title and charge details are generated on save (editable below).</div></div>
        </div>
        <div class="fs-body">
            <div class="row g-3">
                <div class="col-lg-5">
                    <label class="form-label">Service Category</label>
                    <select class="form-select" id="serviceCategory" name="service_category_id">
                        <option value="">— none —</option>
                        @foreach ($serviceCategories as $c)
                            <option value="{{ $c->id }}" @selected(old('service_category_id', $document->service_category_id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-7">
                    <label class="form-label" id="standardsPickerLabel">Standards / Programs</label>
                    <input class="form-control mb-2" id="standardSearch" placeholder="Search (e.g. ISO 9001, GRS, BSCI, Energy Audit)" autocomplete="off">
                    <div id="standardOptions" style="max-height:190px;overflow:auto;border:1px solid #e6e2f5;border-radius:8px;padding:6px 8px;background:#fff"></div>
                    <div id="standardTokens" class="d-flex flex-wrap gap-2 mt-2"></div>
                    <div id="standardHidden"></div>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
    <script>
    (function () {
        const categories = @json($categoriesJson);
        const preselected = @json($selectedStandardIds);
        const catSelect = document.getElementById('serviceCategory');
        const search = document.getElementById('standardSearch');
        const optionsBox = document.getElementById('standardOptions');
        const tokensBox = document.getElementById('standardTokens');
        const hiddenBox = document.getElementById('standardHidden');
        const pickerLabel = document.getElementById('standardsPickerLabel');
        const titleInput = document.getElementById('chargeTitle'); // invoice only
        // Ordered selection: array of {id, name, code}
        let selected = [];

        function byId(catId) { return categories.find(c => String(c.id) === String(catId)); }

        function allStandards() {
            return categories.flatMap(c => c.standards.map(s => ({ ...s, categoryId: c.id })));
        }

        // Seed from existing snapshot (edit): resolve names from the master list.
        (preselected || []).forEach(id => {
            const found = allStandards().find(s => String(s.id) === String(id));
            if (found) selected.push({ id: found.id, name: found.name, code: found.code });
        });

        function renderHidden() {
            hiddenBox.innerHTML = selected.map(s => `<input type="hidden" name="standards[]" value="${s.id}">`).join('');
        }

        function renderTokens() {
            tokensBox.innerHTML = '';
            selected.forEach(s => {
                const chip = document.createElement('span');
                chip.className = 'badge-soft b-service d-inline-flex align-items-center gap-1';
                chip.innerHTML = `<span>${s.code || s.name}</span>`;
                const x = document.createElement('button');
                x.type = 'button'; x.className = 'btn-close btn-close-sm'; x.style.fontSize = '.6rem';
                x.addEventListener('click', () => { selected = selected.filter(v => v.id !== s.id); sync(); });
                chip.appendChild(x);
                tokensBox.appendChild(chip);
            });
        }

        function currentList() {
            const cat = byId(catSelect.value);
            const list = cat ? cat.standards : allStandards();
            const term = (search.value || '').toLowerCase();
            return list.filter(s => !term || (s.name + ' ' + (s.code || '')).toLowerCase().includes(term));
        }

        function renderOptions() {
            const list = currentList();
            optionsBox.innerHTML = '';
            if (!list.length) { optionsBox.innerHTML = '<div class="text-secondary small p-1">No matches.</div>'; return; }
            list.forEach(s => {
                const id = 'std_' + s.id;
                const wrap = document.createElement('label');
                wrap.className = 'd-flex align-items-start gap-2 py-1';
                wrap.style.cursor = 'pointer';
                const cb = document.createElement('input');
                cb.type = 'checkbox'; cb.className = 'form-check-input mt-1'; cb.value = s.id; cb.id = id;
                cb.checked = selected.some(v => String(v.id) === String(s.id));
                cb.addEventListener('change', () => {
                    if (cb.checked) { if (!selected.some(v => v.id === s.id)) selected.push({ id: s.id, name: s.name, code: s.code }); }
                    else { selected = selected.filter(v => String(v.id) !== String(s.id)); }
                    renderTokens(); renderHidden();
                });
                const txt = document.createElement('span');
                txt.className = 'small';
                txt.innerHTML = s.code && s.code !== s.name ? `<strong>${s.code}</strong> — ${s.name}` : s.name;
                wrap.appendChild(cb); wrap.appendChild(txt);
                optionsBox.appendChild(wrap);
            });
        }

        function updateLabel() {
            const cat = byId(catSelect.value);
            pickerLabel.textContent = cat ? cat.label : 'Standards / Programs';
        }

        function sync() { updateLabel(); renderOptions(); renderTokens(); renderHidden(); }

        catSelect.addEventListener('change', () => { search.value = ''; sync(); });
        search.addEventListener('input', renderOptions);
        sync();
    })();
    </script>
    @endpush
@endisset

{{-- 2 · Services & charge presentation --}}
@php
    $chargeMode = old('charge_presentation', $document->charge_presentation ?? 'itemized');
    $firstItem = $document->items?->first();
    $serviceOptions = $services->map(fn ($s) => ['id' => $s->id, 'label' => ($s->short_name ?: $s->name)])->values();
@endphp
@if ($isQuotation)
    <div class="form-section">
        <div class="fs-head">
            <span class="fs-ico"><x-icon name="services" /></span>
            <div><div class="fs-t">Services &amp; Scope</div><div class="fs-s">Pick a service to auto-fill its description, scope and rate.</div></div>
            <button class="btn btn-outline-primary btn-sm ms-auto" type="button" id="addItem"><x-icon name="plus" :size="15" /> Add Service Line</button>
        </div>
        <div class="fs-body">@include('documents._charge_itemized')</div>
    </div>
@else
    <div class="form-section">
        <div class="fs-head">
            <span class="fs-ico"><x-icon name="invoice" /></span>
            <div><div class="fs-t">Charge Presentation</div><div class="fs-s">Choose how the charges appear on this invoice.</div></div>
        </div>
        <div class="fs-body">
            @php $curOptions = ['BDT', 'USD', 'EUR', 'GBP']; $curValue = old('currency', $document->currency ?: ($settings->default_currency ?: 'BDT')); @endphp
            <div class="row g-3 mb-2">
                <div class="col-md-4">
                    <label class="form-label">Reference No. <span class="text-secondary fw-normal">(optional)</span></label>
                    <input class="form-control" name="reference_no" value="{{ old('reference_no', $document->reference_no) }}" placeholder="Separate from the invoice number">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Currency</label>
                    <select class="form-select" name="currency">
                        @foreach ($curOptions as $cur)<option value="{{ $cur }}" @selected($curValue === $cur)>{{ $cur }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Conversion Rate <span class="text-secondary fw-normal">(1 unit = BDT)</span></label>
                    <input class="form-control num" type="number" step="0.0001" min="0" name="conversion_rate" value="{{ old('conversion_rate', $document->conversion_rate ? rtrim(rtrim(number_format((float) $document->conversion_rate, 4, '.', ''), '0'), '.') : null) }}" placeholder="Blank = single currency">
                </div>
            </div>
            <div class="row g-3 mb-2">
                <div class="col-md-4">
                    <label class="form-label">Charge Presentation</label>
                    <select class="form-select" name="charge_presentation" id="chargePresentation">
                        @foreach (\App\Models\ProformaInvoice::PRESENTATIONS as $value => $label)
                            <option value="{{ $value }}" @selected($chargeMode === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4" id="chargeTitleWrap">
                    <label class="form-label">Service / Package Title</label>
                    <input class="form-control" name="charge_title" id="chargeTitle" value="{{ old('charge_title', $document->charge_title) }}" placeholder="e.g. Energy Audit · the SERVICE row">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Site Name <span class="text-secondary fw-normal">(optional)</span></label>
                    <input class="form-control" name="site_name" value="{{ old('site_name', $document->site_name) }}" placeholder="Defaults to client company name">
                </div>
            </div>

            {{-- Consolidated --}}
            <div data-mode-panel="consolidated" class="mode-panel {{ $chargeMode === 'consolidated' ? '' : 'd-none' }}">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Service (optional)</label>
                        <select class="form-select" name="consolidated[service_id]" data-charge-service>
                            <option value="">— none —</option>
                            @foreach ($serviceOptions as $opt)<option value="{{ $opt['id'] }}" @selected(old('consolidated.service_id', $chargeMode === 'consolidated' ? $firstItem?->service_id : null) == $opt['id'])>{{ $opt['label'] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="consolidated[description]" rows="3" placeholder="Certification Fee, including the audit, certification, licence fees and transportation costs.">{{ old('consolidated.description', $chargeMode === 'consolidated' ? $firstItem?->description : '') }}</textarea></div>
                    <div class="col-md-4"><label class="form-label">Amount ({{ $currency }})</label><input class="form-control num" type="number" step="0.01" min="0" name="consolidated[amount]" value="{{ old('consolidated.amount', $chargeMode === 'consolidated' ? $firstItem?->amount : null) }}"></div>
                </div>
            </div>

            {{-- Breakdown — one total --}}
            <div data-mode-panel="component_breakdown" class="mode-panel {{ $chargeMode === 'component_breakdown' ? '' : 'd-none' }}">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Service (optional)</label>
                        <select class="form-select" name="breakdown[service_id]" data-charge-service>
                            <option value="">— none —</option>
                            @foreach ($serviceOptions as $opt)<option value="{{ $opt['id'] }}" @selected(old('breakdown.service_id', $chargeMode === 'component_breakdown' ? $firstItem?->service_id : null) == $opt['id'])>{{ $opt['label'] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12"><label class="form-label">Components (one per line — no individual prices)</label><textarea class="form-control" name="breakdown[components]" rows="5" placeholder="SLCP Verification Fee (Step-03)&#10;Verification Initiation &amp; Upload Fee&#10;Administration Fee&#10;Travel &amp; Operational Cost">{{ old('breakdown.components', $chargeMode === 'component_breakdown' ? implode("\n", $firstItem?->scope_items ?? []) : '') }}</textarea></div>
                    <div class="col-md-4"><label class="form-label">Unit (optional)</label><input class="form-control" name="breakdown[unit]" value="{{ old('breakdown.unit', $chargeMode === 'component_breakdown' ? $firstItem?->unit : '') }}"></div>
                    <div class="col-md-4"><label class="form-label">Total Amount ({{ $currency }})</label><input class="form-control num" type="number" step="0.01" name="breakdown[amount]" value="{{ old('breakdown.amount', $chargeMode === 'component_breakdown' ? $firstItem?->amount : null) }}"></div>
                </div>
                <div class="form-hint mt-2">One consolidated amount applies to the whole package.</div>
            </div>

            {{-- Itemized --}}
            <div data-mode-panel="itemized" class="mode-panel {{ $chargeMode === 'itemized' ? '' : 'd-none' }}">
                <div class="d-flex justify-content-end mb-2">
                    <button class="btn btn-outline-primary btn-sm" type="button" id="addItem"><x-icon name="plus" :size="15" /> Add Row</button>
                </div>
                @include('documents._charge_itemized')
            </div>
        </div>
    </div>
@endif

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
        subtotal += parseFloat(row.querySelector('[data-amount-input]')?.value || 0);
    });
    const adjustment = parseFloat(document.querySelector('[name="adjustment"]')?.value || 0);
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
    if (event.target.matches('.calc, .amount-input, [name="vat_rate"]')) recalc();
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
    const description = row.querySelector('[data-description]');
    if (description) description.value = {{ $isQuotation ? 'service.quotation_scope || service.default_description || service.name' : 'service.invoice_description || service.default_description || service.name' }};
    const scope = row.querySelector('[data-scope-items]');
    if (scope) scope.value = (service.components || [])
        .filter(component => component.is_active)
        .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
        .map(component => component.name)
        .join("\n");
    const amount = row.querySelector('[data-amount-input]');
    if (amount && !parseFloat(amount.value || 0)) amount.value = service.default_rate || 0;
    recalc();
});

document.addEventListener('click', event => {
    const removeBtn = event.target.closest('[data-remove-row]');
    if (! removeBtn) return;
    if (tableBody.querySelectorAll('tr').length > 1) removeBtn.closest('tr').remove();
    recalc();
});

recalc();

// Charge presentation mode switching (invoice form only).
const chargePresentation = document.getElementById('chargePresentation');
if (chargePresentation) {
    const modePanels = document.querySelectorAll('[data-mode-panel]');
    const titleWrap = document.getElementById('chargeTitleWrap');

    function applyChargeMode() {
        const mode = chargePresentation.value;
        modePanels.forEach(panel => {
            const active = panel.dataset.modePanel === mode;
            panel.classList.toggle('d-none', !active);
            // Disable inactive inputs so hidden required fields never block submit.
            panel.querySelectorAll('input, textarea, select').forEach(el => { el.disabled = !active; });
        });
        if (mode === 'itemized') recalc();
    }

    chargePresentation.addEventListener('change', applyChargeMode);
    applyChargeMode();

    document.querySelectorAll('[data-charge-service]').forEach(select => {
        select.addEventListener('change', () => {
            const panel = select.closest('[data-mode-panel]');
            const mode = panel.dataset.modePanel;
            const service = services[select.value];
            const title = document.getElementById('chargeTitle');

            // Selecting a service is an explicit request to load that service's
            // defaults, so we OVERWRITE the title/description/components. Choosing
            // "— none —" leaves whatever the user has typed (custom invoice).
            if (! service) return;

            if (title) title.value = service.short_name || service.name || '';
            const defaultRate = parseFloat(service.default_rate || 0);

            if (mode === 'consolidated') {
                const desc = panel.querySelector('[name="consolidated[description]"]');
                if (desc) desc.value = service.invoice_description || service.default_description || service.name || '';
                const amount = panel.querySelector('[name="consolidated[amount]"]');
                if (amount && defaultRate > 0) amount.value = service.default_rate;
            } else {
                const components = (service.components || [])
                    .filter(c => c.is_active)
                    .sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0))
                    .map(c => c.name).join('\n');
                const textarea = panel.querySelector('[name="breakdown[components]"]');
                if (textarea) textarea.value = components;
                const amount = panel.querySelector('[name="breakdown[amount]"]');
                if (amount && defaultRate > 0) amount.value = service.default_rate;
            }
        });
    });
}
</script>
@endpush
