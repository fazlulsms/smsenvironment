@extends('layouts.app', ['title' => 'AI Commercial Draft'])

@php
    $badge = fn ($status) => match ($status) {
        'matched' => ['b-ok', '✓ Matched'],
        'suggested' => ['b-warn', '? Suggested'],
        'not_matched' => ['b-danger', '✗ Not Matched'],
        default => ['b-neutral', 'Needs Review'],
    };
    $requestFormat = "COMPANY / ENTITY:\nCLIENT:\nSITE:\nCONTACT PERSON:\nDOCUMENT:\nSERVICE:\nSTANDARD / PACKAGE:\nCHARGE DETAILS:\nAMOUNT:\nCURRENCY:\nCONVERSION RATE:\nBANK:\nEMAIL:\nCC:\nREFERENCE / NOTE:";
    $currencies = ['BDT', 'USD', 'EUR', 'GBP'];
@endphp

@section('content')
<x-page-toolbar title="AI Commercial Draft" subtitle="Paste a WhatsApp/email request → review → apply. The AI only drafts; you still save through the normal form.">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('proforma-invoices.create') }}"><x-icon name="plus" :size="15" /> New Invoice</a>
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('quotations.create') }}"><x-icon name="plus" :size="15" /> New Quotation</a>
</x-page-toolbar>

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="row g-4">
    {{-- Left: instruction --}}
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <form method="post" action="{{ route('ai-draft.analyze') }}">
                @csrf
                <label class="form-label fw-semibold">Business Entity</label>
                <select class="form-select mb-3" name="entity_id">
                    @foreach ($entities as $e)
                        <option value="{{ $e->id }}" @selected($currentEntityId == $e->id)>{{ $e->name }}</option>
                    @endforeach
                </select>

                <div class="d-flex align-items-center justify-content-between">
                    <label class="form-label fw-semibold mb-0">Paste Commercial Instruction</label>
                    <button class="btn btn-outline-primary btn-sm" type="button" id="copyFormat" data-format="{{ $requestFormat }}"><x-icon name="copy" :size="14" /> Copy Request Format</button>
                </div>
                <textarea class="form-control my-2" name="instruction" rows="13" required placeholder="Paste the invoice/quotation request received from WhatsApp…">{{ $instruction }}</textarea>
                @error('instruction')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                <button class="btn btn-primary w-100" type="submit"><x-icon name="sparkles" :size="16" /> Analyze with Gemini</button>
                <div class="form-hint mt-2">Nothing is saved or issued here. Analyze only prepares a draft for review.</div>
            </form>
        </div></div>
    </div>

    {{-- Right: review --}}
    <div class="col-lg-7">
        @if (! $draft)
            @if (! empty($analyzeMessage) && ! $analyzeOk)
                <div class="alert alert-warning"><strong>Unable to analyze automatically.</strong> {{ $analyzeMessage }} Your text is preserved on the left.</div>
            @else
                <div class="card"><div class="card-body text-secondary text-center py-5">
                    <div class="mb-2"><x-icon name="sparkles" :size="28" /></div>
                    Select the issuing entity, paste the request, and press <strong>Analyze with Gemini</strong>.<br>
                    The detected draft will appear here for review before you apply it.
                </div></div>
            @endif
        @else
            @php $dt = $draft['document_type']['value']; @endphp
            <form method="post" action="{{ route('ai-draft.apply') }}">
                @csrf
                <input type="hidden" name="entity_id" value="{{ $draft['entity']['id'] }}">

                <div class="card mb-3"><div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="fw-semibold">Detected Draft</div>
                        <span class="badge-soft b-neutral">Entity: {{ $draft['entity']['name'] }}</span>
                    </div>
                    <div class="text-secondary small mb-3">{{ $analyzeMessage }} Correct anything below before applying.</div>

                    <div class="row g-3">
                        {{-- Client --}}
                        <div class="col-md-7">
                            <label class="form-label">Client @php([$c,$l]=$badge($draft['client']['status']))<span class="badge-soft {{ $c }} ms-1">{{ $l }}</span></label>
                            <select class="form-select" name="client_id">
                                <option value="">— Create new{{ $draft['client']['detected'] ? ': '.$draft['client']['detected'] : '' }} —</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(($draft['client']['id'] ?? null) == $client->id)>{{ $client->company_name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="new_client_name" value="{{ $draft['client']['detected'] ?? '' }}">
                            <input type="hidden" name="new_client_email" value="{{ $draft['email'] ?? '' }}">
                            <input type="hidden" name="new_client_contact" value="{{ $draft['contact_person'] ?? '' }}">
                            @if (! ($draft['client']['id'] ?? null))<div class="form-hint mt-1">No match — a new client will be prefilled (not created until you save).</div>@endif
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Document Type</label>
                            <select class="form-select" name="charge_presentation_doc" disabled>
                                <option>{{ $dt === 'quotation' ? 'Quotation' : ($dt === 'proforma_invoice' ? 'Proforma Invoice' : 'Choose on apply') }}</option>
                            </select>
                        </div>

                        {{-- Service category + standards --}}
                        <div class="col-md-7">
                            <label class="form-label">Service Category @php([$c,$l]=$badge($draft['service_category']['status']))<span class="badge-soft {{ $c }} ms-1">{{ $l }}</span></label>
                            <select class="form-select" name="service_category_id">
                                <option value="">— none —</option>
                                @foreach ($serviceCategories as $cat)
                                    <option value="{{ $cat->id }}" @selected(($draft['service_category']['id'] ?? null) == $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Charge Presentation</label>
                            <select class="form-select" name="charge_presentation" id="dPresentation">
                                @foreach (['consolidated' => 'Consolidated Fee', 'component_breakdown' => 'Fee Breakdown — One Total', 'itemized' => 'Itemized Charges'] as $v => $lbl)
                                    <option value="{{ $v }}" @selected($draft['charge_presentation']['value'] === $v)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label mb-1">Standards / Services / Packages</label>
                            <div class="d-flex flex-wrap gap-2">
                                @forelse ($draft['standards'] as $s)
                                    @php([$c,$l]=$badge($s['status']))
                                    <span class="badge-soft {{ $c }}">{{ $s['code'] ?: $s['name'] }} · {{ $l }}</span>
                                    @if ($s['id'])<input type="hidden" name="standards[]" value="{{ $s['id'] }}">@endif
                                @empty
                                    <span class="text-secondary small">None detected — add them in the form after applying.</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="col-12"><label class="form-label">Service / Particular (title)</label>
                            <input class="form-control" name="charge_title" value="{{ old('charge_title') }}" placeholder="Leave blank to auto-generate from the standards">
                        </div>
                    </div>
                </div></div>

                {{-- Charge details by presentation --}}
                <div class="card mb-3"><div class="card-body">
                    <div class="fw-semibold mb-2">Charge Details</div>

                    <div data-dmode="consolidated component_breakdown">
                        <label class="form-label" data-label-consolidated>Description / Charge For</label>
                        <textarea class="form-control mb-2" name="description" rows="2" placeholder="Leave blank to auto-generate">{{ old('description') }}</textarea>
                        <label class="form-label">Components (one per line)</label>
                        <textarea class="form-control mb-2" name="components" rows="3" placeholder="One particular per line">{{ old('components', collect($draft['charge_particulars'])->pluck('name')->implode("\n")) }}</textarea>
                        <label class="form-label">Total Amount ({{ $draft['currency']['value'] }})</label>
                        <input class="form-control num" type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $draft['totals']['subtotal']) }}">
                    </div>

                    <div data-dmode="itemized" class="mt-2">
                        <table class="table table-sm align-middle"><thead><tr><th>Service / Particular</th><th style="width:32%">Amount ({{ $draft['currency']['value'] }})</th></tr></thead>
                        <tbody id="dItems">
                            @forelse ($draft['itemized_rows'] as $row)
                                <tr>
                                    <td><input class="form-control form-control-sm" name="item_description[]" value="{{ $row['description'] }}"></td>
                                    <td><input class="form-control form-control-sm num" type="number" step="0.01" name="item_amount[]" value="{{ $row['amount'] }}"></td>
                                </tr>
                            @empty
                                <tr><td><input class="form-control form-control-sm" name="item_description[]" value=""></td><td><input class="form-control form-control-sm num" type="number" step="0.01" name="item_amount[]" value="0"></td></tr>
                            @endforelse
                        </tbody></table>
                        <button class="btn btn-outline-primary btn-sm" type="button" id="dAddItem"><x-icon name="plus" :size="14" /> Add Line</button>
                    </div>
                </div></div>

                {{-- Currency, bank, totals --}}
                <div class="card mb-3"><div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Currency @if($draft['currency']['defaulted'])<span class="badge-soft b-warn ms-1">Defaulted</span>@endif</label>
                            <select class="form-select" name="currency">
                                @foreach ($currencies as $cur)<option value="{{ $cur }}" @selected($draft['currency']['value'] === $cur)>{{ $cur }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Conversion Rate</label>
                            <input class="form-control num" type="number" step="0.0001" min="0" name="conversion_rate" value="{{ old('conversion_rate', $draft['conversion_rate']) }}" placeholder="1 unit = BDT">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bank @php([$c,$l]=$badge($draft['bank']['status']))<span class="badge-soft {{ $c }} ms-1">{{ $l }}</span></label>
                            <select class="form-select" name="bank_account_id">
                                <option value="">— select —</option>
                                @foreach ($banks as $bank)<option value="{{ $bank->id }}" @selected(($draft['bank']['id'] ?? null) == $bank->id)>{{ $bank->bank_name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Reference No.</label><input class="form-control" name="reference_no" value="{{ old('reference_no', $draft['reference']) }}"></div>
                        <div class="col-md-6"><label class="form-label">Site Name</label><input class="form-control" name="site_name" value="{{ old('site_name', $draft['site_name']) }}"></div>
                    </div>
                    <div class="d-flex justify-content-end gap-4 mt-3 pt-2 border-top">
                        <div class="text-end"><div class="text-secondary small">Subtotal ({{ $draft['totals']['currency'] }})</div><div class="fw-bold">{{ number_format($draft['totals']['subtotal'], 2) }}</div></div>
                        @if ($draft['totals']['bdt_equivalent'])
                            <div class="text-end"><div class="text-secondary small">Equivalent (BDT) @ {{ number_format($draft['totals']['rate'], 2) }}</div><div class="fw-bold" style="color:var(--brand)">{{ number_format($draft['totals']['bdt_equivalent'], 2) }}</div></div>
                        @endif
                    </div>
                    <div class="form-hint mt-1">Totals are calculated by the application, not the AI. Final totals are recalculated when you save.</div>
                </div></div>

                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn {{ $dt === 'proforma_invoice' ? 'btn-primary' : 'btn-outline-primary' }}" type="submit" name="target" value="proforma_invoice"><x-icon name="invoice" :size="16" /> Apply to Proforma Invoice</button>
                    <button class="btn {{ $dt === 'quotation' ? 'btn-primary' : 'btn-outline-primary' }}" type="submit" name="target" value="quotation"><x-icon name="quotation" :size="16" /> Apply to Quotation</button>
                </div>
            </form>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.getElementById('copyFormat')?.addEventListener('click', async (e) => {
    try { await navigator.clipboard.writeText(e.currentTarget.dataset.format); e.currentTarget.innerHTML = '✓ Copied'; }
    catch { /* clipboard blocked — ignore */ }
});
const dp = document.getElementById('dPresentation');
if (dp) {
    const apply = () => document.querySelectorAll('[data-dmode]').forEach(el => {
        el.style.display = el.dataset.dmode.split(' ').includes(dp.value) ? '' : 'none';
    });
    dp.addEventListener('change', apply); apply();
    document.getElementById('dAddItem')?.addEventListener('click', () => {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input class="form-control form-control-sm" name="item_description[]"></td><td><input class="form-control form-control-sm num" type="number" step="0.01" name="item_amount[]" value="0"></td>';
        document.getElementById('dItems').appendChild(tr);
    });
}
</script>
@endpush
@endsection
