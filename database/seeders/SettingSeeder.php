<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Setting::query()->firstOrCreate([], [
            'organization_name' => 'SMS Environmental Alliance',
            'logo_path' => 'logos/smsea-logo.png',
            'tagline' => 'Environmental testing, assessment and compliance support',
            'office_address' => '01, Sonargaon Janapath Avenue, Sector #12, Uttara, Model Town, Dhaka-1230, Bangladesh',
            'phone' => '+8801873035178',
            'email' => 'info@smsenvironment.com',
            'website' => 'www.smsenvironment.com',
            'default_currency' => 'BDT',
            'currency_major_name' => 'Taka',
            'currency_minor_name' => 'Paisa',
            'prepared_by_name' => 'Authorized Representative',
            'prepared_by_designation' => 'SMS Environmental Alliance',
            'default_payment_terms' => "Payment Requirement: Payment shall be made according to the agreed commercial arrangement.\nMode of Payment: Payment should be made by bank transfer or account payee cheque.\nCommencement: Work schedule will be confirmed following acceptance and applicable payment or formal authorization.\nAdditional Scope: Any work outside the agreed scope may be subject to additional quotation and approval.",
            'quotation_subject_pattern' => 'Quotation for {services} of {client}',
            'quotation_intro_text' => null,
            'quotation_closing_text' => 'We trust that this proposal meets your requirements and look forward to supporting your environmental compliance needs.',
            'quotation_validity_text' => 'This quotation is valid for 30 days from the date of issue unless otherwise stated.',
            'quotation_vat_treatment' => 'exclusive',
            'quotation_show_vat_separately' => true,
            'quotation_vat_note' => 'Quoted fees are exclusive of VAT. Applicable VAT shall be added or borne as required under prevailing regulations.',
            'quotation_ait_note' => 'VAT and AIT/withholding tax shall be treated in accordance with applicable statutory requirements.',
            'quotation_terms_conditions' => "Scope of Service: Services will be performed according to the scope described in this quotation. Activities outside the agreed scope may require additional fees or written agreement.\n\nClient Cooperation and Scheduling: The client will provide reasonable access to relevant areas, personnel, documents and information. Assessment or testing dates will be mutually agreed and remain subject to availability and site readiness.\n\nReporting and Confidentiality: Reports will be prepared based on information, observations, measurements and data available during the assignment. Information obtained during the assignment will be treated as confidential, subject to applicable legal or regulatory requirements.\n\nPayment and Validity: Payment shall follow the commercial terms stated in this quotation. The quotation remains valid for the stated validity period unless otherwise agreed in writing.",
            'quotation_include_acceptance' => true,
            'quotation_acceptance_text' => 'We hereby confirm acceptance of the scope of services, commercial terms and conditions stated in this quotation and authorize SMS Environmental Alliance to proceed accordingly.',
            'invoice_charge_for_pattern' => '{services}',
            'invoice_payment_terms' => "Payment should be made by account payee cheque or bank transfer.\nVAT/AIT will be treated as per applicable government rules.",
            'footer_text' => 'SMS Environmental Alliance | info@smsenvironment.com | www.smsenvironment.com | +8801873035178',
            'pdf_note' => 'This is a computer-generated document.',
            'quotation_number_format' => 'SMSEA/QT/{YYYY}/{####}',
            'invoice_number_format' => 'SMSEA/PI/{YYYY}/{####}',
        ]);
    }
}
