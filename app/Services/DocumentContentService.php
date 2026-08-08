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
            'intro_text' => $settings->quotation_intro_text
                ?: 'We are pleased to submit our financial proposal for the requested environmental assessment/testing services.',
            'compliance_note' => $this->combinedText($services->pluck('compliance_note')->filter())
                ?: $settings->quotation_compliance_note,
            'closing_text' => $settings->quotation_closing_text,
            'validity_text' => $settings->quotation_validity_text,
            'payment_terms' => $settings->default_payment_terms,
            'notes' => $settings->quotation_default_notes,
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
