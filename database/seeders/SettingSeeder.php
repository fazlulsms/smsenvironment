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
            'quotation_email_subject_template' => 'Quotation for {{service_name}} - {{client_name}}',
            'quotation_email_body_template' => "Dear {{contact_person}},\n\nGreetings from SMS Environmental Alliance.\n\nPlease find attached our quotation for {{service_name}} for your review and consideration.\n\nShould you require any clarification or further information regarding the scope, commercial terms or proposed service, please feel free to contact us.\n\nWe look forward to supporting your environmental assessment and compliance requirements.\n\nBest regards,\n\n{{prepared_by}}\n{{designation}}\nSMS Environmental Alliance\n{{phone}}\n{{email}}",
            'quotation_intro_text' => null,
            'quotation_closing_text' => 'We trust that this proposal meets your requirements and look forward to supporting your environmental compliance needs.',
            'quotation_validity_text' => 'This quotation is valid for 30 days from the date of issue unless otherwise stated.',
            'quotation_vat_treatment' => 'exclusive',
            'quotation_show_vat_separately' => true,
            'quotation_vat_note' => 'Quoted fees are exclusive of VAT. Applicable VAT shall be added or borne as required under prevailing regulations.',
            'quotation_ait_note' => 'VAT and AIT/withholding tax shall be treated in accordance with applicable statutory requirements.',
            'quotation_terms_conditions' => "Scope of Service: Services shall be performed according to the scope stated in this quotation. Additional activity outside the agreed scope may require written confirmation and additional fees.\n\nScheduling and Site Readiness: Assessment or testing dates will be mutually agreed based on scope, site readiness and technical team availability.\n\nChanges and Additional Work: Additional locations, testing points, parameters or material scope changes may require revision of the fee, timeline or quotation.\n\nReporting: Reports will be prepared based on observations, measurements, records and information available within the agreed service scope.\n\nConfidentiality: Assignment information will be treated confidentially and used for the intended service, subject to applicable legal or regulatory obligations.\n\nFees and Payment: Fees and payment shall follow the commercial and payment terms stated in this quotation.\n\nVAT, AIT and Statutory Treatment: VAT, AIT or withholding tax and other statutory deductions shall be treated according to the stated tax treatment and applicable requirements.\n\nProposal Validity: This quotation remains valid for the stated validity period unless otherwise agreed in writing.\n\nCancellation / Rescheduling: Confirmed schedules may be revised subject to operational availability, site readiness and any cost already incurred.",
            'quotation_include_acceptance' => true,
            'quotation_acceptance_text' => 'We hereby confirm acceptance of the scope of services, commercial terms and conditions stated in this quotation and authorize SMS Environmental Alliance to proceed accordingly.',
            'invoice_charge_for_pattern' => '{services}',
            'proforma_invoice_email_subject_template' => 'Proforma Invoice for {{service_name}} - {{client_name}}',
            'proforma_invoice_email_body_template' => "Dear {{contact_person}},\n\nGreetings from SMS Environmental Alliance.\n\nPlease find attached the Proforma Invoice for {{service_name}}.\n\nKindly review the attached document and proceed with the payment as per the stated terms.\n\nPlease feel free to contact us if any clarification is required.\n\nBest regards,\n\n{{prepared_by}}\n{{designation}}\nSMS Environmental Alliance\n{{phone}}\n{{email}}",
            'invoice_payment_terms' => "100% advance payment is required before scheduling or commencing the assignment. Payment may be made by cash, account payee cheque, pay order or bank transfer in favour of SMS Environmental Alliance.\nVAT and AIT shall be treated as stated in the Proforma Invoice. Where not included, applicable VAT shall be added to the payable amount and AIT shall be deducted at source in accordance with prevailing laws.\nPlease mention the Proforma Invoice reference when making payment or sharing payment confirmation.",
            'footer_text' => 'SMS Environmental Alliance | info@smsenvironment.com | www.smsenvironment.com | +8801873035178',
            'pdf_note' => 'This is a computer-generated document.',
            'quotation_number_format' => 'SMSEA/QT/{YYYY}/{####}',
            'invoice_number_format' => 'SMSEA/PI/{YYYY}/{####}',
        ]);
    }
}
