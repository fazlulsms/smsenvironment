<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Support\Collection;

class DocumentContentService
{
    public function quotationDefaults(Client $client, Collection $services, Setting $settings): array
    {
        $serviceNames = $this->serviceNames($services);

        return [
            'subject' => $this->render(
                $this->quotationSubjectTemplate($services, $settings),
                $client,
                $serviceNames
            ),
            'intro_text' => $this->quotationIntro($settings, $services),
            'compliance_note' => $this->combinedText($services->pluck('compliance_note')->filter())
                ?: $settings->quotation_compliance_note,
            'closing_text' => $settings->quotation_closing_text,
            'validity_text' => $settings->quotation_validity_text,
            'payment_terms' => $this->quotationPaymentTerms($settings),
            'notes' => $settings->quotation_default_notes,
            'vat_treatment' => $settings->quotation_vat_treatment ?: 'exclusive',
            'vat_rate' => $settings->quotation_vat_rate,
            'show_vat_separately' => (bool) ($settings->quotation_show_vat_separately ?? true),
            'vat_note' => $settings->quotation_vat_note,
            'ait_note' => $settings->quotation_ait_note,
            'terms_conditions' => $settings->quotation_terms_conditions ?: $this->defaultTermsConditions(),
            'include_acceptance' => (bool) ($settings->quotation_include_acceptance ?? true),
            'acceptance_text' => $settings->quotation_acceptance_text ?: 'We hereby confirm acceptance of the scope of services, commercial terms and conditions stated in this quotation and authorize SMS Environmental Alliance to proceed accordingly.',
        ];
    }

    public function invoiceDefaults(Client $client, Collection $services, Setting $settings): array
    {
        $serviceNames = $this->serviceNames($services, 'invoice');

        return [
            'charge_for' => $this->render(
                $settings->invoice_charge_for_pattern ?: '{services}',
                $client,
                $serviceNames
            ),
            'payment_terms' => $settings->invoice_payment_terms ?: $settings->default_payment_terms,
            'validity_text' => $settings->invoice_validity_text,
            'notes' => $settings->invoice_default_notes,
        ];
    }

    public function serviceDescription(?Service $service, string $type): ?string
    {
        if (! $service) {
            return null;
        }

        if ($type === 'quotation') {
            return $service->quotation_scope ?: $service->default_description ?: $service->name;
        }

        return $service->invoice_description ?: $service->default_description ?: $service->name;
    }

    private function serviceNames(Collection $services, string $type = 'quotation'): string
    {
        $names = $services
            ->map(function (Service $service) use ($type) {
                if ($type === 'invoice' && $service->invoice_description) {
                    return $service->invoice_description;
                }

                return $service->short_name ?: $service->name;
            })
            ->filter()
            ->unique()
            ->values();

        return $this->joinHumanList($names);
    }

    private function quotationSubjectTemplate(Collection $services, Setting $settings): string
    {
        if ($services->count() === 1 && $services->first()->quotation_subject_template) {
            return $services->first()->quotation_subject_template;
        }

        return $settings->quotation_subject_pattern ?: 'Quotation for {services} of {client}';
    }

    private function render(string $template, Client $client, string $serviceNames): string
    {
        return strtr($template, [
            '{client}' => $client->company_name,
            '{services}' => $serviceNames,
            '{service}' => $serviceNames,
        ]);
    }

    private function professionalQuotationIntro(Collection $services): string
    {
        $category = $this->serviceCategory($services);
        $serviceSentence = match ($category) {
            'testing' => 'The proposed service includes testing, measurement, analysis and reporting for the requested environmental parameters.',
            'consultancy' => 'The proposed service includes document review, assessment, data analysis, development of relevant plans or measures and reporting.',
            default => 'The proposed service includes document review, site assessment, data collection, analysis and reporting for the requested environmental assessment assignment.',
        };

        return "Thank you for the opportunity to submit this quotation for your consideration.\n\nSMS Environmental Alliance is pleased to submit this professional proposal for the requested environmental assessment, testing and consultancy service. {$serviceSentence}\n\nWhere applicable, the assignment will consider relevant national regulatory requirements, client requirements and applicable technical reference frameworks configured for the selected service.\n\nWe trust that this proposal meets your requirements and remain available for any clarification required prior to confirmation.";
    }

    private function quotationIntro(Setting $settings, Collection $services): string
    {
        $legacyIntro = 'We are pleased to submit our financial proposal for the requested environmental assessment/testing services.';

        if ($settings->quotation_intro_text && trim($settings->quotation_intro_text) !== $legacyIntro) {
            return $settings->quotation_intro_text;
        }

        return $this->professionalQuotationIntro($services);
    }

    private function quotationPaymentTerms(Setting $settings): ?string
    {
        $legacyTerms = "Payment should be made by account payee cheque or bank transfer.\nWork will commence after confirmation of payment where applicable.";

        if ($settings->default_payment_terms && trim($settings->default_payment_terms) !== $legacyTerms) {
            return $settings->default_payment_terms;
        }

        return "Payment Requirement: Payment shall be made according to the agreed commercial arrangement.\nMode of Payment: Payment should be made by bank transfer or account payee cheque.\nCommencement: Work schedule will be confirmed following acceptance and applicable payment or formal authorization.\nAdditional Scope: Any work outside the agreed scope may be subject to additional quotation and approval.";
    }

    private function serviceCategory(Collection $services): string
    {
        $text = $services
            ->map(fn (Service $service) => strtolower($service->name.' '.$service->default_description.' '.$service->quotation_scope))
            ->implode(' ');

        if (str_contains($text, 'test') || str_contains($text, 'parameter') || str_contains($text, 'emission') || str_contains($text, 'noise')) {
            return 'testing';
        }

        if (str_contains($text, 'plan') || str_contains($text, 'management') || str_contains($text, 'audit')) {
            return 'consultancy';
        }

        return 'assessment';
    }

    private function defaultTermsConditions(): string
    {
        return "Scope of Service: Services will be performed according to the scope described in this quotation. Activities outside the agreed scope may require additional fees or written agreement.\n\nClient Cooperation and Scheduling: The client will provide reasonable access to relevant areas, personnel, documents and information. Assessment or testing dates will be mutually agreed and remain subject to availability and site readiness.\n\nReporting and Confidentiality: Reports will be prepared based on information, observations, measurements and data available during the assignment. Information obtained during the assignment will be treated as confidential, subject to applicable legal or regulatory requirements.\n\nPayment and Validity: Payment shall follow the commercial terms stated in this quotation. The quotation remains valid for the stated validity period unless otherwise agreed in writing.";
    }

    private function combinedText(Collection $parts): ?string
    {
        $text = $parts
            ->map(fn (string $part) => trim($part))
            ->filter()
            ->unique()
            ->implode("\n\n");

        return $text !== '' ? $text : null;
    }

    private function joinHumanList(Collection $items): string
    {
        $items = $items->values();

        if ($items->count() <= 1) {
            return $items->first() ?: 'Environmental Services';
        }

        if ($items->count() === 2) {
            return $items[0].' and '.$items[1];
        }

        return $items->slice(0, -1)->implode(', ').' and '.$items->last();
    }
}
