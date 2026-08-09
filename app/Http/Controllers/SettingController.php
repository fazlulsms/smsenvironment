<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', ['settings' => Setting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = Setting::current();
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'office_address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'default_currency' => ['required', 'string', 'max:20'],
            'currency_major_name' => ['required', 'string', 'max:50'],
            'currency_minor_name' => ['required', 'string', 'max:50'],
            'prepared_by_name' => ['nullable', 'string', 'max:255'],
            'prepared_by_designation' => ['nullable', 'string', 'max:255'],
            'default_payment_terms' => ['nullable', 'string'],
            'quotation_subject_pattern' => ['nullable', 'string', 'max:255'],
            'quotation_email_subject_template' => ['nullable', 'string', 'max:255'],
            'quotation_email_body_template' => ['nullable', 'string'],
            'quotation_intro_text' => ['nullable', 'string'],
            'quotation_compliance_note' => ['nullable', 'string'],
            'quotation_closing_text' => ['nullable', 'string'],
            'quotation_validity_text' => ['nullable', 'string'],
            'quotation_default_notes' => ['nullable', 'string'],
            'quotation_scope_assessment' => ['nullable', 'string'],
            'quotation_methodology' => ['nullable', 'string'],
            'quotation_deliverables' => ['nullable', 'string'],
            'quotation_client_responsibilities' => ['nullable', 'string'],
            'quotation_vat_treatment' => ['required', 'in:exclusive,included,add,not_applicable'],
            'quotation_vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quotation_show_vat_separately' => ['nullable', 'boolean'],
            'quotation_vat_note' => ['nullable', 'string'],
            'quotation_ait_note' => ['nullable', 'string'],
            'quotation_terms_conditions' => ['nullable', 'string'],
            'quotation_include_acceptance' => ['nullable', 'boolean'],
            'quotation_acceptance_text' => ['nullable', 'string'],
            'invoice_charge_for_pattern' => ['nullable', 'string', 'max:255'],
            'proforma_invoice_email_subject_template' => ['nullable', 'string', 'max:255'],
            'proforma_invoice_email_body_template' => ['nullable', 'string'],
            'default_email_cc' => ['nullable', 'string'],
            'invoice_payment_terms' => ['nullable', 'string'],
            'invoice_validity_text' => ['nullable', 'string'],
            'invoice_default_notes' => ['nullable', 'string'],
            'footer_text' => ['nullable', 'string'],
            'pdf_note' => ['nullable', 'string'],
            'quotation_number_format' => ['required', 'string', 'max:255'],
            'invoice_number_format' => ['required', 'string', 'max:255'],
        ]);

        unset($data['logo']);
        $data['quotation_show_vat_separately'] = $request->boolean('quotation_show_vat_separately');
        $data['quotation_include_acceptance'] = $request->boolean('quotation_include_acceptance');

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $settings->update($data);

        return redirect()->route('settings.edit')->with('status', 'Settings updated.');
    }
}
