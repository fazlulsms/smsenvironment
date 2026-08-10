@extends('layouts.app', ['title' => 'Settings'])

@php
    $aiProvider = config('services.ai.provider');
    $aiConfigured = $aiProvider === 'gemini'
        ? filled(config('services.ai.gemini.key')) && filled(config('services.ai.gemini.model'))
        : ($aiProvider === 'openai' && filled(config('services.ai.openai.key')) && filled(config('services.ai.openai.model')));
    $mailer = config('mail.default');
    $emailConfigured = ! in_array($mailer, ['log', 'array', null], true) && filled(config('mail.from.address'));
@endphp

@section('content')
<x-page-toolbar title="Settings" subtitle="Organization identity, document defaults, email and Smart Paste configuration.">
    <x-slot:actions>
        @if ($emailConfigured)
            <span class="badge-soft b-ok"><x-icon name="check" :size="12" />Email configured</span>
        @else
            <span class="badge-soft b-warn"><x-icon name="alert" :size="12" />Email: {{ $mailer === 'log' ? 'log only' : 'not configured' }}</span>
        @endif
        @if ($aiConfigured)
            <span class="badge-soft b-ok"><x-icon name="check" :size="12" />Smart Paste: {{ ucfirst($aiProvider) }}</span>
        @else
            <span class="badge-soft b-warn"><x-icon name="alert" :size="12" />Smart Paste: local only</span>
        @endif
    </x-slot:actions>
</x-page-toolbar>

<form method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data" data-loading>
    @csrf
    @method('put')

    {{-- Organization --}}
    <div class="form-section">
        <div class="fs-head"><span class="fs-ico"><x-icon name="building" /></span><div><div class="fs-t">Organization</div><div class="fs-s">Identity shown across the app and documents.</div></div></div>
        <div class="fs-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Organization Name</label><input class="form-control" name="organization_name" value="{{ old('organization_name', $settings->organization_name) }}" required></div>
                <div class="col-md-6"><label class="form-label">Logo</label><input class="form-control" type="file" name="logo">
                    @if ($settings->logo_path)<div class="form-hint">Current logo saved. Upload to replace.</div>@endif</div>
                <div class="col-md-12"><label class="form-label">Tagline</label><input class="form-control" name="tagline" value="{{ old('tagline', $settings->tagline) }}"></div>
                <div class="col-md-4"><label class="form-label">Default Currency</label><input class="form-control" name="default_currency" value="{{ old('default_currency', $settings->default_currency) }}" required></div>
                <div class="col-md-4"><label class="form-label">Currency Major Name</label><input class="form-control" name="currency_major_name" value="{{ old('currency_major_name', $settings->currency_major_name ?? 'Taka') }}" required></div>
                <div class="col-md-4"><label class="form-label">Currency Minor Name</label><input class="form-control" name="currency_minor_name" value="{{ old('currency_minor_name', $settings->currency_minor_name ?? 'Paisa') }}" required></div>
                <div class="col-12"><label class="form-label">Office Address</label><textarea class="form-control" name="office_address">{{ old('office_address', $settings->office_address) }}</textarea></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ old('phone', $settings->phone) }}"></div>
                <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $settings->email) }}"></div>
                <div class="col-md-4"><label class="form-label">Website</label><input class="form-control" name="website" value="{{ old('website', $settings->website) }}"></div>
                <div class="col-md-6"><label class="form-label">Prepared By Name</label><input class="form-control" name="prepared_by_name" value="{{ old('prepared_by_name', $settings->prepared_by_name) }}"></div>
                <div class="col-md-6"><label class="form-label">Prepared By Designation</label><input class="form-control" name="prepared_by_designation" value="{{ old('prepared_by_designation', $settings->prepared_by_designation) }}"></div>
            </div>
        </div>
    </div>

    {{-- Document defaults --}}
    <div class="form-section">
        <div class="fs-head"><span class="fs-ico"><x-icon name="settings" /></span><div><div class="fs-t">Document Defaults</div><div class="fs-s">Numbering, patterns and footer shared by both documents.</div></div></div>
        <div class="fs-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Quotation Number Format</label><input class="form-control" name="quotation_number_format" value="{{ old('quotation_number_format', $settings->quotation_number_format) }}" required></div>
                <div class="col-md-6"><label class="form-label">Invoice Number Format</label><input class="form-control" name="invoice_number_format" value="{{ old('invoice_number_format', $settings->invoice_number_format) }}" required></div>
                <div class="col-md-6"><label class="form-label">Quotation Subject Pattern</label><input class="form-control" name="quotation_subject_pattern" value="{{ old('quotation_subject_pattern', $settings->quotation_subject_pattern) }}" placeholder="Quotation for {services} of {client}"></div>
                <div class="col-md-6"><label class="form-label">Invoice Charge For Pattern</label><input class="form-control" name="invoice_charge_for_pattern" value="{{ old('invoice_charge_for_pattern', $settings->invoice_charge_for_pattern) }}" placeholder="{services}"></div>
                <div class="col-12"><label class="form-label">Default Payment Terms</label><textarea class="form-control" name="default_payment_terms">{{ old('default_payment_terms', $settings->default_payment_terms) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Footer Text</label><textarea class="form-control" name="footer_text">{{ old('footer_text', $settings->footer_text) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">PDF Note</label><textarea class="form-control" name="pdf_note">{{ old('pdf_note', $settings->pdf_note) }}</textarea></div>
            </div>
        </div>
    </div>

    {{-- Quotation content --}}
    <details class="adv" open>
        <summary><x-icon name="quotation" :size="16" /> Quotation Content <span class="chev"><x-icon name="chevron-left" :size="14" style="transform:rotate(-90deg)" /></span></summary>
        <div class="adv-body">
            <div class="row g-3">
                <div class="col-12"><label class="form-label">Quotation Introduction</label><textarea class="form-control" name="quotation_intro_text">{{ old('quotation_intro_text', $settings->quotation_intro_text) }}</textarea></div>
                <div class="col-12"><label class="form-label">Default Quotation Compliance Note</label><textarea class="form-control" name="quotation_compliance_note">{{ old('quotation_compliance_note', $settings->quotation_compliance_note) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Quotation Closing Text</label><textarea class="form-control" name="quotation_closing_text">{{ old('quotation_closing_text', $settings->quotation_closing_text) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Quotation Validity Text</label><textarea class="form-control" name="quotation_validity_text">{{ old('quotation_validity_text', $settings->quotation_validity_text) }}</textarea></div>
                <div class="col-12"><label class="form-label">Quotation Default Notes</label><textarea class="form-control" name="quotation_default_notes">{{ old('quotation_default_notes', $settings->quotation_default_notes) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Generic Scope of Assessment</label><textarea class="form-control" rows="5" name="quotation_scope_assessment">{{ old('quotation_scope_assessment', $settings->quotation_scope_assessment) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Generic Assessment Methodology</label><textarea class="form-control" rows="5" name="quotation_methodology">{{ old('quotation_methodology', $settings->quotation_methodology) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Generic Deliverables</label><textarea class="form-control" rows="5" name="quotation_deliverables">{{ old('quotation_deliverables', $settings->quotation_deliverables) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Client Responsibilities</label><textarea class="form-control" rows="5" name="quotation_client_responsibilities">{{ old('quotation_client_responsibilities', $settings->quotation_client_responsibilities) }}</textarea></div>
                <div class="col-md-4">
                    <label class="form-label">Default VAT Treatment</label>
                    <select class="form-select" name="quotation_vat_treatment">
                        @foreach (['exclusive' => 'Exclusive of VAT', 'included' => 'VAT Included', 'add' => 'Add VAT', 'not_applicable' => 'Not Applicable'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('quotation_vat_treatment', $settings->quotation_vat_treatment ?? 'exclusive') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Default VAT Rate (%)</label><input class="form-control" type="number" step="0.001" name="quotation_vat_rate" value="{{ old('quotation_vat_rate', $settings->quotation_vat_rate) }}"></div>
                <div class="col-md-4 d-flex align-items-end"><label class="form-check"><input class="form-check-input" type="checkbox" name="quotation_show_vat_separately" value="1" @checked(old('quotation_show_vat_separately', $settings->quotation_show_vat_separately ?? true))> <span class="form-check-label">Show VAT separately</span></label></div>
                <div class="col-md-6"><label class="form-label">VAT Note</label><textarea class="form-control" name="quotation_vat_note">{{ old('quotation_vat_note', $settings->quotation_vat_note) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">AIT / Withholding Note</label><textarea class="form-control" name="quotation_ait_note">{{ old('quotation_ait_note', $settings->quotation_ait_note) }}</textarea></div>
                <div class="col-12"><label class="form-label">Standard Terms &amp; Conditions</label><textarea class="form-control" rows="7" name="quotation_terms_conditions">{{ old('quotation_terms_conditions', $settings->quotation_terms_conditions) }}</textarea></div>
                <div class="col-md-4 d-flex align-items-end"><label class="form-check"><input class="form-check-input" type="checkbox" name="quotation_include_acceptance" value="1" @checked(old('quotation_include_acceptance', $settings->quotation_include_acceptance ?? true))> <span class="form-check-label">Include Acceptance Section</span></label></div>
                <div class="col-md-8"><label class="form-label">Acceptance Wording</label><textarea class="form-control" name="quotation_acceptance_text">{{ old('quotation_acceptance_text', $settings->quotation_acceptance_text) }}</textarea></div>
            </div>
        </div>
    </details>

    {{-- Proforma invoice content --}}
    <details class="adv">
        <summary><x-icon name="invoice" :size="16" /> Proforma Invoice Content <span class="chev"><x-icon name="chevron-left" :size="14" style="transform:rotate(-90deg)" /></span></summary>
        <div class="adv-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Invoice Payment Terms</label><textarea class="form-control" name="invoice_payment_terms">{{ old('invoice_payment_terms', $settings->invoice_payment_terms) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Invoice Validity Text</label><textarea class="form-control" name="invoice_validity_text">{{ old('invoice_validity_text', $settings->invoice_validity_text) }}</textarea></div>
                <div class="col-12"><label class="form-label">Invoice Default Notes</label><textarea class="form-control" name="invoice_default_notes">{{ old('invoice_default_notes', $settings->invoice_default_notes) }}</textarea></div>
            </div>
        </div>
    </details>

    {{-- Email --}}
    <div class="form-section">
        <div class="fs-head"><span class="fs-ico"><x-icon name="email" /></span><div><div class="fs-t">Email Templates</div><div class="fs-s">Auto-filled subject and body. Placeholders like <code>@{{service_name}}</code> and <code>@{{client_name}}</code> are supported.</div></div></div>
        <div class="fs-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Quotation Email Subject</label><input class="form-control" name="quotation_email_subject_template" value="{{ old('quotation_email_subject_template', $settings->quotation_email_subject_template) }}" placeholder="Quotation for @{{service_name}} - @{{client_name}}"></div>
                <div class="col-md-6"><label class="form-label">Proforma Invoice Email Subject</label><input class="form-control" name="proforma_invoice_email_subject_template" value="{{ old('proforma_invoice_email_subject_template', $settings->proforma_invoice_email_subject_template) }}" placeholder="Proforma Invoice for @{{service_name}} - @{{client_name}}"></div>
                <div class="col-md-6"><label class="form-label">Quotation Email Body</label><textarea class="form-control" rows="9" name="quotation_email_body_template">{{ old('quotation_email_body_template', $settings->quotation_email_body_template) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Proforma Invoice Email Body</label><textarea class="form-control" rows="9" name="proforma_invoice_email_body_template">{{ old('proforma_invoice_email_body_template', $settings->proforma_invoice_email_body_template) }}</textarea></div>
                <div class="col-12"><label class="form-label">Default Email CC</label><input class="form-control" name="default_email_cc" value="{{ old('default_email_cc', $settings->default_email_cc) }}" placeholder="finance@example.com, coordinator@example.com"></div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end sticky-bottom py-2">
        <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> Save Settings</button>
    </div>
</form>

{{-- Email test + AI status --}}
<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-head"><h2>Test Email Configuration</h2>
                @if ($emailConfigured)<span class="badge-soft b-ok ms-auto"><x-icon name="check" :size="12" />Ready</span>
                @else<span class="badge-soft b-warn ms-auto"><x-icon name="alert" :size="12" />{{ $mailer === 'log' ? 'Log driver' : 'Not configured' }}</span>@endif
            </div>
            <div class="card-body">
                <p class="form-hint mb-3">Send one test message to confirm SMTP delivery. Credentials are configured in <code>.env</code>, never here.</p>
                <form method="post" action="{{ route('settings.test-email') }}" data-loading>
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-8"><label class="form-label">Test Recipient</label>
                            <input class="form-control" type="email" name="test_email" value="{{ old('test_email', $settings->email) }}" required></div>
                        <div class="col-sm-4"><button class="btn btn-outline-primary w-100" type="submit"><x-icon name="send" :size="16" /> Send Test</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-head"><h2>Smart Paste AI</h2></div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    @if ($aiConfigured)
                        <span class="badge-soft b-ok"><x-icon name="sparkles" :size="12" />{{ ucfirst($aiProvider) }} configured</span>
                    @else
                        <span class="badge-soft b-neutral"><x-icon name="sparkles" :size="12" />Local extraction only</span>
                    @endif
                </div>
                <p class="form-hint mb-0">Smart Paste uses AI to read messy pasted client details, then falls back to local extraction. Provider &amp; API keys are set in <code>.env</code>.</p>
            </div>
        </div>
    </div>
</div>
@endsection
