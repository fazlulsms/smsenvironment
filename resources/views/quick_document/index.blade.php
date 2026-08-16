@extends('layouts.app', ['title' => 'Quick Environmental Document'])

@section('content')
<x-page-toolbar title="Quick Environmental Document"
    subtitle="Fast input for EIA & Environmental Parameter Testing under SMS Environmental Alliance. Prepare opens the normal form, fully prefilled — nothing is saved or numbered until you Save there.">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('proforma-invoices.create') }}"><x-icon name="plus" :size="15" /> New Invoice</a>
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('quotations.create') }}"><x-icon name="plus" :size="15" /> New Quotation</a>
</x-page-toolbar>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if (! $hasBank)
    <div class="alert alert-warning"><strong>No active SMSEA bank found.</strong> The document can still be prepared, but you must select a bank on the create form before saving.</div>
@endif

<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <form method="post" action="{{ route('quick-env.prepare') }}">
            @csrf

            <div class="card mb-3"><div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="fw-semibold">New Environmental Document</div>
                    <span class="badge-soft b-neutral">Entity: {{ $entity->name }}</span>
                </div>

                {{-- Service --}}
                <label class="form-label fw-semibold">Service</label>
                <div class="row g-2 mb-3">
                    @foreach ($services as $key => $svc)
                        <div class="col-md-6">
                            <label class="qsvc @error('service') is-invalid @enderror">
                                <input type="radio" name="service" value="{{ $key }}" @checked(old('service', $selectedService) === $key) required>
                                <span class="qsvc-body">
                                    <span class="qsvc-title">{{ $svc['label'] }}</span>
                                    <span class="qsvc-hint">{{ $svc['hint'] }}</span>
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('service')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                <div class="row g-3">
                    {{-- Client --}}
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Client</label>
                        <select class="form-select" name="client_id" required data-quick-client>
                            <option value="">— search / select client —</option>
                            @foreach ($clients as $c)
                                <option value="{{ $c->id }}" @selected(old('client_id') == $c->id)>{{ $c->company_name }}</option>
                            @endforeach
                        </select>
                        @error('client_id')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="form-hint mt-1"><a href="{{ route('clients.create') }}" target="_blank">+ Add a new client</a></div>
                    </div>

                    {{-- Document type --}}
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Document Type</label>
                        <select class="form-select" name="document_type" required>
                            <option value="proforma_invoice" @selected(old('document_type', 'proforma_invoice') === 'proforma_invoice')>Proforma Invoice</option>
                            <option value="quotation" @selected(old('document_type') === 'quotation')>Quotation</option>
                        </select>
                        @error('document_type')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    {{-- Amount --}}
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text" data-currency-prefix>{{ old('currency', 'BDT') }}</span>
                            <input class="form-control" type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required placeholder="0.00">
                        </div>
                        @error('amount')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div></div>

            {{-- Advanced (collapsed) --}}
            <div class="card mb-3"><div class="card-body">
                <button class="btn btn-link p-0 text-decoration-none fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#quickAdvanced">
                    <x-icon name="settings" :size="15" /> Advanced options
                </button>
                <div class="collapse mt-3 {{ $errors->hasAny(['currency', 'conversion_rate', 'bank_account_id', 'site_name', 'reference_no', 'vat_treatment', 'vat_rate']) ? 'show' : '' }}" id="quickAdvanced">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Currency</label>
                            <select class="form-select" name="currency" data-currency-select>
                                @foreach ($currencies as $cur)
                                    <option value="{{ $cur }}" @selected(old('currency', 'BDT') === $cur)>{{ $cur }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Conversion Rate <span class="text-secondary fw-normal">(→ BDT)</span></label>
                            <input class="form-control" type="number" step="0.0001" min="0" name="conversion_rate" value="{{ old('conversion_rate') }}" placeholder="e.g. 118">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Bank Account</label>
                            <select class="form-select" name="bank_account_id">
                                <option value="">— default SMSEA bank —</option>
                                @foreach ($banks as $b)
                                    <option value="{{ $b->id }}" @selected(old('bank_account_id', $defaultBankId) == $b->id)>{{ $b->bank_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Site Name</label>
                            <input class="form-control" name="site_name" value="{{ old('site_name') }}" placeholder="Optional site / project name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference No.</label>
                            <input class="form-control" name="reference_no" value="{{ old('reference_no') }}" placeholder="Optional client reference">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">VAT Treatment</label>
                            <select class="form-select" name="vat_treatment">
                                <option value="">— use default —</option>
                                @foreach (['exclusive' => 'Exclusive of VAT', 'included' => 'VAT Included', 'add' => 'Add VAT', 'not_applicable' => 'Not Applicable'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('vat_treatment') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">VAT Rate (%)</label>
                            <input class="form-control" type="number" step="0.001" min="0" name="vat_rate" value="{{ old('vat_rate') }}" placeholder="e.g. 15">
                        </div>
                    </div>
                    <div class="form-hint mt-2">Leave blank to use the SMSEA defaults. Currency defaults to BDT.</div>
                </div>
            </div></div>

            <div class="d-flex justify-content-end gap-2">
                <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Prepare Document</button>
            </div>
            <div class="form-hint text-end mt-2">Prepare opens the normal create form with everything filled in. No number is consumed and nothing is saved until you Save/Preview there.</div>
        </form>
    </div>
</div>

@push('scripts')
<style>
    .qsvc { display:block; cursor:pointer; border:1px solid var(--border, #dee2e6); border-radius:.6rem; padding:.75rem .9rem; margin:0; transition:border-color .15s, background .15s; }
    .qsvc input { margin-right:.5rem; }
    .qsvc:has(input:checked) { border-color:var(--brand, #198754); background:rgba(25,135,84,.06); }
    .qsvc-body { display:inline-flex; flex-direction:column; }
    .qsvc-title { font-weight:600; }
    .qsvc-hint { font-size:.8rem; color:var(--text-muted, #6c757d); }
</style>
<script>
    // Keep the amount prefix in sync with the chosen currency.
    (function () {
        const sel = document.querySelector('[data-currency-select]');
        const prefix = document.querySelector('[data-currency-prefix]');
        if (sel && prefix) {
            sel.addEventListener('change', () => { prefix.textContent = sel.value; });
        }
    })();
</script>
@endpush
@endsection
