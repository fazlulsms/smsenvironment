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
            'scope_assessment' => $settings->quotation_scope_assessment ?: $this->defaultScopeAssessment($services),
            'methodology' => $settings->quotation_methodology ?: $this->defaultMethodology($services),
            'deliverables' => $settings->quotation_deliverables ?: $this->defaultDeliverables($services),
            'client_responsibilities' => $settings->quotation_client_responsibilities ?: $this->defaultClientResponsibilities($services),
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
            'payment_terms' => $settings->invoice_payment_terms ?: $this->defaultInvoicePaymentTerms(),
            'validity_text' => $settings->invoice_validity_text,
            'notes' => $settings->invoice_default_notes,
            'vat_treatment' => $settings->quotation_vat_treatment ?: 'exclusive',
            'vat_rate' => $settings->quotation_vat_rate,
            'show_vat_separately' => (bool) ($settings->quotation_show_vat_separately ?? true),
        ];
    }

    private function defaultInvoicePaymentTerms(): string
    {
        return "100% advance payment is required before scheduling or commencing the assignment. Payment may be made by cash, account payee cheque, pay order or bank transfer in favour of SMS Environmental Alliance.\nVAT and AIT shall be treated as stated in the Proforma Invoice. Where not included, applicable VAT shall be added to the payable amount and AIT shall be deducted at source in accordance with prevailing laws.\nPlease mention the Proforma Invoice reference when making payment or sharing payment confirmation.";
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
        return "Scope of Service: Services shall be performed according to the scope stated in this quotation. Additional activity outside the agreed scope may require written confirmation and additional fees.\n\nScheduling and Site Readiness: Assessment or testing dates will be mutually agreed based on scope, site readiness and technical team availability.\n\nChanges and Additional Work: Additional locations, testing points, parameters or material scope changes may require revision of the fee, timeline or quotation.\n\nReporting: Reports will be prepared based on observations, measurements, records and information available within the agreed service scope.\n\nConfidentiality: Assignment information will be treated confidentially and used for the intended service, subject to applicable legal or regulatory obligations.\n\nFees and Payment: Fees and payment shall follow the commercial and payment terms stated in this quotation.\n\nVAT, AIT and Statutory Treatment: VAT, AIT or withholding tax and other statutory deductions shall be treated according to the stated tax treatment and applicable requirements.\n\nProposal Validity: This quotation remains valid for the stated validity period unless otherwise agreed in writing.\n\nCancellation / Rescheduling: Confirmed schedules may be revised subject to operational availability, site readiness and any cost already incurred.";
    }

    private function defaultScopeAssessment(Collection $services): string
    {
        $category = $this->serviceCategory($services);
        $items = [
            'Review relevant documents, records, permits, previous reports and available monitoring data.',
            'Assess relevant facility operations, processes, utilities and environmental aspects.',
        ];

        if ($category === 'testing') {
            $items[] = 'Conduct environmental measurements, monitoring and/or parameter testing where included.';
        }

        if ($category === 'consultancy') {
            $items[] = 'Develop mitigation, monitoring, management or improvement actions where included.';
        }

        if ($this->containsServiceText($services, ['energy'])) {
            $items[] = 'Review energy consumption, major energy-using systems and efficiency opportunities.';
        }

        $items[] = 'Evaluate aspects, impacts, risks, results and applicable compliance requirements.';
        $items[] = 'Prepare professional reports with findings, observations and recommendations.';

        return $this->lines($items);
    }

    private function defaultMethodology(Collection $services): string
    {
        $items = [
            'The assignment will combine document/data review, onsite assessment or inspection, relevant information collection, analysis of findings and preparation of the applicable report and recommendations.',
        ];

        if ($this->serviceCategory($services) === 'testing') {
            $items[] = 'Environmental measurements, monitoring or testing will be performed where included.';
        }

        if ($this->containsServiceText($services, ['management plan', 'emp'])) {
            $items[] = 'Where EMP is included, mitigation, monitoring and management measures will be developed.';
        }

        if ($this->containsServiceText($services, ['energy'])) {
            $items[] = 'Energy audit work will include energy-use data and performance analysis where applicable.';
        }

        if ($this->containsServiceText($services, ['environmental and social', 'esia'])) {
            $items[] = 'ESIA work will consider relevant environmental and social impact/risk evaluation where applicable.';
        }

        return $this->lines($items);
    }

    private function defaultDeliverables(Collection $services): string
    {
        $items = [
            'Environmental assessment / technical report.',
            'Findings against applicable requirements.',
            'Identified risks, impacts or compliance gaps where applicable.',
            'Recommended corrective, mitigation or improvement measures.',
        ];

        if ($this->serviceCategory($services) === 'testing') {
            $items[] = 'Test and monitoring results where applicable.';
        }

        if ($this->containsServiceText($services, ['management plan', 'emp'])) {
            $items[] = 'Environmental Management Plan where included.';
        }

        return $this->lines($items);
    }

    private function defaultClientResponsibilities(Collection $services): string
    {
        $items = [
            'Provide reasonable access to relevant facility areas, personnel, documents, records, operational information, utilities and resources required for the assignment.',
        ];

        if ($this->serviceCategory($services) === 'testing') {
            $items[] = 'Facilitate access to relevant sampling or monitoring locations and operating conditions where environmental testing is included.';
        }

        return $this->lines($items);
    }

    private function containsServiceText(Collection $services, array $needles): bool
    {
        $text = $services
            ->map(fn (Service $service) => strtolower($service->name.' '.$service->short_name.' '.$service->default_description.' '.$service->quotation_scope))
            ->implode(' ');

        foreach ($needles as $needle) {
            if (str_contains($text, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function lines(array $items): string
    {
        return collect($items)
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->implode("\n");
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
