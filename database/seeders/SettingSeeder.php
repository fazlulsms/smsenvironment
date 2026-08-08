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
            'default_payment_terms' => "Payment should be made by account payee cheque or bank transfer.\nWork will commence after confirmation of payment where applicable.",
            'quotation_subject_pattern' => 'Quotation for {services} of {client}',
            'quotation_intro_text' => 'We are pleased to submit our financial proposal for the requested environmental assessment/testing services.',
            'quotation_closing_text' => 'We look forward to supporting your environmental compliance requirements.',
            'quotation_validity_text' => 'This quotation is valid for 30 days from the date of issue unless otherwise stated.',
            'invoice_charge_for_pattern' => '{services}',
            'invoice_payment_terms' => "Payment should be made by account payee cheque or bank transfer.\nVAT/AIT will be treated as per applicable government rules.",
            'footer_text' => 'SMS Environmental Alliance | info@smsenvironment.com | www.smsenvironment.com | +8801873035178',
            'pdf_note' => 'This is a computer-generated document.',
            'quotation_number_format' => 'SMSEA/QT/{YYYY}/{####}',
            'invoice_number_format' => 'SMSEA/PI/{YYYY}/{####}',
        ]);
    }
}
