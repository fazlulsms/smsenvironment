@extends('layouts.app', ['title' => 'Settings'])

@section('content')
<h1 class="h3 mb-3">Settings</h1>
<div class="panel p-3 mb-3">
    <div class="muted-label">Smart Paste AI</div>
    @php
        $aiProvider = config('services.ai.provider');
        $aiConfigured = $aiProvider === 'gemini'
            ? filled(config('services.ai.gemini.key')) && filled(config('services.ai.gemini.model'))
            : ($aiProvider === 'openai' && filled(config('services.ai.openai.key')) && filled(config('services.ai.openai.model')));
    @endphp
    <div class="fw-semibold">
        {{ $aiConfigured ? ucfirst($aiProvider).' - Configured' : 'Not configured' }}
    </div>
</div>
<form class="panel p-3" method="post" action="{{ route('settings.update') }}" enctype="multipart/form-data">
    @csrf
    @method('put')
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Organization Name</label><input class="form-control" name="organization_name" value="{{ old('organization_name', $settings->organization_name) }}" required></div>
        <div class="col-md-6"><label class="form-label">Logo</label><input class="form-control" type="file" name="logo"></div>
        <div class="col-md-6"><label class="form-label">Tagline</label><input class="form-control" name="tagline" value="{{ old('tagline', $settings->tagline) }}"></div>
        <div class="col-md-4"><label class="form-label">Default Currency</label><input class="form-control" name="default_currency" value="{{ old('default_currency', $settings->default_currency) }}" required></div>
        <div class="col-md-4"><label class="form-label">Currency Major Name</label><input class="form-control" name="currency_major_name" value="{{ old('currency_major_name', $settings->currency_major_name ?? 'Taka') }}" required></div>
        <div class="col-md-4"><label class="form-label">Currency Minor Name</label><input class="form-control" name="currency_minor_name" value="{{ old('currency_minor_name', $settings->currency_minor_name ?? 'Paisa') }}" required></div>
        <div class="col-12"><label class="form-label">Office Address</label><textarea class="form-control" name="office_address">{{ old('office_address', $settings->office_address) }}</textarea></div>
        <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ old('phone', $settings->phone) }}"></div>
        <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $settings->email) }}"></div>
        <div class="col-md-4"><label class="form-label">Website</label><input class="form-control" name="website" value="{{ old('website', $settings->website) }}"></div>
        <div class="col-md-6"><label class="form-label">Prepared By Name</label><input class="form-control" name="prepared_by_name" value="{{ old('prepared_by_name', $settings->prepared_by_name) }}"></div>
        <div class="col-md-6"><label class="form-label">Prepared By Designation</label><input class="form-control" name="prepared_by_designation" value="{{ old('prepared_by_designation', $settings->prepared_by_designation) }}"></div>
        <div class="col-12"><label class="form-label">Default Payment Terms</label><textarea class="form-control" name="default_payment_terms">{{ old('default_payment_terms', $settings->default_payment_terms) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Quotation Subject Pattern</label><input class="form-control" name="quotation_subject_pattern" value="{{ old('quotation_subject_pattern', $settings->quotation_subject_pattern) }}" placeholder="Quotation for {services} of {client}"></div>
        <div class="col-md-6"><label class="form-label">Invoice Charge For Pattern</label><input class="form-control" name="invoice_charge_for_pattern" value="{{ old('invoice_charge_for_pattern', $settings->invoice_charge_for_pattern) }}" placeholder="{services}"></div>
        <div class="col-12"><label class="form-label">Quotation Introduction</label><textarea class="form-control" name="quotation_intro_text">{{ old('quotation_intro_text', $settings->quotation_intro_text) }}</textarea></div>
        <div class="col-12"><label class="form-label">Default Quotation Compliance Note</label><textarea class="form-control" name="quotation_compliance_note">{{ old('quotation_compliance_note', $settings->quotation_compliance_note) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Quotation Closing Text</label><textarea class="form-control" name="quotation_closing_text">{{ old('quotation_closing_text', $settings->quotation_closing_text) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Quotation Validity Text</label><textarea class="form-control" name="quotation_validity_text">{{ old('quotation_validity_text', $settings->quotation_validity_text) }}</textarea></div>
        <div class="col-12"><label class="form-label">Quotation Default Notes</label><textarea class="form-control" name="quotation_default_notes">{{ old('quotation_default_notes', $settings->quotation_default_notes) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Invoice Payment Terms</label><textarea class="form-control" name="invoice_payment_terms">{{ old('invoice_payment_terms', $settings->invoice_payment_terms) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Invoice Validity Text</label><textarea class="form-control" name="invoice_validity_text">{{ old('invoice_validity_text', $settings->invoice_validity_text) }}</textarea></div>
        <div class="col-12"><label class="form-label">Invoice Default Notes</label><textarea class="form-control" name="invoice_default_notes">{{ old('invoice_default_notes', $settings->invoice_default_notes) }}</textarea></div>
        <div class="col-12"><label class="form-label">Footer Text</label><textarea class="form-control" name="footer_text">{{ old('footer_text', $settings->footer_text) }}</textarea></div>
        <div class="col-12"><label class="form-label">PDF Note</label><textarea class="form-control" name="pdf_note">{{ old('pdf_note', $settings->pdf_note) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Quotation Number Format</label><input class="form-control" name="quotation_number_format" value="{{ old('quotation_number_format', $settings->quotation_number_format) }}" required></div>
        <div class="col-md-6"><label class="form-label">Invoice Number Format</label><input class="form-control" name="invoice_number_format" value="{{ old('invoice_number_format', $settings->invoice_number_format) }}" required></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Save Settings</button></div>
</form>
@endsection
