<?php

namespace App\Services;

use App\Models\ProformaInvoice;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class DocumentFilenameService
{
    private const MAX_FILENAME_LENGTH = 145;

    private const MAX_SERVICE_SEGMENT_LENGTH = 72;

    public function quotationFilename(Quotation $quotation): string
    {
        $quotation->loadMissing('items.service');

        return $this->filename(
            'Quotation',
            $this->clientName($quotation->client_snapshot),
            $this->serviceSegment($quotation->items)
        );
    }

    public function proformaInvoiceFilename(ProformaInvoice $invoice): string
    {
        $invoice->loadMissing('items.service');

        return $this->filename(
            'Proforma Invoice',
            $this->clientName($invoice->client_snapshot),
            $this->serviceSegment($invoice->items)
        );
    }

    private function filename(string $documentName, string $clientName, ?string $serviceSegment): string
    {
        $parts = [$documentName, $clientName];

        if (filled($serviceSegment)) {
            $parts[] = $serviceSegment;
        }

        $filename = $this->sanitize(implode(' - ', array_filter($parts))).'.pdf';

        if (mb_strlen($filename) <= self::MAX_FILENAME_LENGTH) {
            return $filename;
        }

        $filename = $this->sanitize($documentName.' - '.$clientName).'.pdf';

        if (mb_strlen($filename) <= self::MAX_FILENAME_LENGTH) {
            return $filename;
        }

        return mb_substr($filename, 0, self::MAX_FILENAME_LENGTH - 4).'.pdf';
    }

    private function clientName(?array $snapshot): string
    {
        return $this->sanitize($snapshot['company_name'] ?? 'Client');
    }

    private function serviceSegment(EloquentCollection $items): ?string
    {
        $names = $items
            ->sortBy('sort_order')
            ->map(fn ($item) => $this->serviceName($item))
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return null;
        }

        if ($names->count() > 3) {
            return 'Multiple Environmental Services';
        }

        $segment = $this->humanList($names);

        return mb_strlen($segment) > self::MAX_SERVICE_SEGMENT_LENGTH
            ? 'Multiple Environmental Services'
            : $segment;
    }

    private function serviceName($item): ?string
    {
        $name = $item->service?->short_name
            ?: $item->service?->name
            ?: $this->descriptionName((string) $item->description);

        $name = $this->sanitize($name);

        return $name === '' ? null : $name;
    }

    private function descriptionName(string $description): string
    {
        $description = trim($description);

        if (str_contains(strtolower($description), ' - inclusive of ')) {
            return explode(' - inclusive of ', $description, 2)[0];
        }

        $sentence = preg_split('/[.;]/', $description)[0] ?? $description;

        return mb_strlen($sentence) > 45
            ? ''
            : $sentence;
    }

    private function humanList(Collection $names): string
    {
        if ($names->count() === 1) {
            return $names->first();
        }

        if ($names->count() === 2) {
            return $names->implode(' & ');
        }

        return $names->slice(0, -1)->implode(', ').' & '.$names->last();
    }

    private function sanitize(string $value): string
    {
        $value = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value, " \t\n\r\0\x0B.-");
    }
}
