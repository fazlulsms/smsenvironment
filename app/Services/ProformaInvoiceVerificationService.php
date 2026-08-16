<?php

namespace App\Services;

use App\Models\ProformaInvoice;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class ProformaInvoiceVerificationService
{
    /** Current payload version for new documents — signs entity code + charge presentation. */
    public const PAYLOAD_VERSION = 'DOC-PI-V3';

    /** Entity-aware payload without charge presentation. */
    public const PAYLOAD_VERSION_V2 = 'DOC-PI-V2';

    /** Original single-entity (SMSEA) payload — kept verifiable, never re-signed. */
    public const LEGACY_PAYLOAD_VERSION = 'SMSEA-PI-V1';

    private const KNOWN_VERSIONS = [
        self::PAYLOAD_VERSION,
        self::PAYLOAD_VERSION_V2,
        self::LEGACY_PAYLOAD_VERSION,
    ];

    public function apply(ProformaInvoice $invoice): ProformaInvoice
    {
        $signature = $this->signature($invoice);

        $invoice->forceFill([
            'verification_payload_version' => self::PAYLOAD_VERSION,
            'verification_signature' => $signature,
            'verification_id' => $this->verificationId($signature),
        ])->save();

        return $invoice;
    }

    public function ensure(ProformaInvoice $invoice): ProformaInvoice
    {
        if (
            in_array($invoice->verification_payload_version, self::KNOWN_VERSIONS, true)
            && filled($invoice->verification_id)
            && filled($invoice->verification_signature)
        ) {
            return $invoice;
        }

        return $this->apply($invoice);
    }

    public function canonicalData(ProformaInvoice $invoice): array
    {
        $invoice->loadMissing('items');
        $client = $invoice->client_snapshot ?: [];
        $settings = $invoice->settings_snapshot ?: [];
        $version = $this->version($invoice);

        $base = [
            'version' => $version,
            'document_type' => 'PROFORMA_INVOICE',
            'reference' => $this->clean($invoice->number),
            'date' => optional($invoice->date)->format('Y-m-d') ?: $this->clean((string) $invoice->date),
            'client' => $this->clean($client['company_name'] ?? ''),
            'client_address' => $this->clean($client['address'] ?? ''),
            'services' => $this->serviceData($invoice),
            'currency' => $this->clean($settings['default_currency'] ?? 'BDT'),
            'net_amount' => $this->money((float) $invoice->subtotal + (float) $invoice->adjustment),
            'vat_treatment' => $this->clean($invoice->vat_treatment ?: 'exclusive'),
            'vat_rate' => number_format((float) ($invoice->vat_rate ?? 0), 3, '.', ''),
            'vat_amount' => $this->money((float) ($invoice->vat_amount ?? 0)),
            'total_amount' => $this->money((float) $invoice->total),
        ];

        // V1: original SMSEA canonical, reproduced exactly.
        if ($version === self::LEGACY_PAYLOAD_VERSION) {
            return $base;
        }

        // V2: entity-aware. V3 additionally signs the charge presentation mode.
        $entityCode = ['entity_code' => $this->clean((string) $invoice->entity_code)];

        if ($version === self::PAYLOAD_VERSION) {
            return $entityCode + ['presentation' => $invoice->charge_presentation ?: 'itemized'] + $base;
        }

        return $entityCode + $base;
    }

    /** The canonical version to sign/verify a document with. New docs use the current version. */
    private function version(ProformaInvoice $invoice): string
    {
        return in_array($invoice->verification_payload_version, self::KNOWN_VERSIONS, true)
            ? $invoice->verification_payload_version
            : self::PAYLOAD_VERSION;
    }

    public function signature(ProformaInvoice $invoice): string
    {
        return hash_hmac('sha256', $this->canonicalString($invoice), $this->secret());
    }

    public function verificationId(string $signature): string
    {
        return implode('-', str_split(strtoupper(substr($signature, 0, 16)), 4));
    }

    public function payloadText(ProformaInvoice $invoice): string
    {
        $invoice = $this->ensure($invoice);
        $data = $this->canonicalData($invoice);
        $settings = $invoice->settings_snapshot ?: [];
        $version = $this->version($invoice);
        $legacy = $version === self::LEGACY_PAYLOAD_VERSION;
        $entityName = $this->clean($settings['organization_name'] ?? 'SMS Environmental Alliance');
        $serviceLines = collect($data['services'])
            ->map(fn (array $service, int $index) => ($index + 1).'. '.$service['name'])
            ->implode("\n");

        $lines = [
            strtoupper($entityName),
            'PROFORMA INVOICE VERIFICATION',
            '',
            'Document: '.$entityName.' Proforma Invoice',
            'Invoice Reference: '.$data['reference'],
            'Invoice Date: '.$data['date'],
            '',
            'Client: '.$data['client'],
            'Client Address: '.$data['client_address'],
            '',
            'Service(s):',
            $serviceLines,
            '',
            'Net Amount: '.$data['currency'].' '.$this->formatAmount($data['net_amount']),
            $this->vatLine($data),
            'Total Payable: '.$data['currency'].' '.$this->formatAmount($data['total_amount']),
            '',
            $legacy ? null : 'Entity: '.$this->clean((string) $invoice->entity_code),
            $version === self::PAYLOAD_VERSION ? 'Charge Presentation: '.($invoice->charge_presentation ?: 'itemized') : null,
            'Verification ID: '.$invoice->verification_id,
            'Payload Version: '.$version,
            'Signature: '.$invoice->verification_signature,
        ];

        return collect($lines)->filter(fn ($line) => $line !== null)->implode("\n");
    }

    /**
     * The scannable content of the QR: a short link to the public verification
     * page. Keeping the QR tiny (a URL, not the whole invoice) makes it reliably
     * scannable; the page then shows the authoritative details from our records.
     */
    public function verificationUrl(ProformaInvoice $invoice): string
    {
        $invoice = $this->ensure($invoice);

        return rtrim((string) config('app.url'), '/').'/verify/'.$invoice->verification_id;
    }

    public function qrSvg(ProformaInvoice $invoice): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(
                180,
                4,
                null,
                null,
                Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(31, 111, 74))
            ),
            new SvgImageBackEnd
        );

        // A short URL keeps the module count low, so higher error correction (Q)
        // still scans easily even at small print sizes.
        return (new Writer($renderer))->writeString(
            $this->verificationUrl($invoice),
            'UTF-8',
            ErrorCorrectionLevel::Q()
        );
    }

    public function qrDataUri(ProformaInvoice $invoice): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->qrSvg($invoice));
    }

    private function canonicalString(ProformaInvoice $invoice): string
    {
        return json_encode(
            $this->canonicalData($invoice),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    private function serviceData(ProformaInvoice $invoice): array
    {
        return $invoice->items
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($item) => [
                'name' => $this->serviceName((string) $item->description),
                'includes' => $this->scopeItems($item->scope_items),
                'amount' => $this->money((float) $item->amount),
            ])
            ->all();
    }

    private function serviceName(string $description): string
    {
        $description = $this->clean($description);

        if (str_contains(strtolower($description), ' - inclusive of ')) {
            return $this->clean(explode(' - inclusive of ', $description, 2)[0]);
        }

        return mb_strlen($description) > 90
            ? mb_substr($description, 0, 87).'...'
            : ($description ?: 'Service');
    }

    private function scopeItems(mixed $scopeItems): array
    {
        return collect(is_array($scopeItems) ? $scopeItems : [])
            ->map(fn ($item) => $this->clean(is_array($item) ? ($item['name'] ?? '') : (string) $item))
            ->filter()
            ->values()
            ->all();
    }

    private function vatLine(array $data): string
    {
        if ($data['vat_treatment'] === 'add' && (float) $data['vat_amount'] > 0) {
            $rate = rtrim(rtrim(number_format((float) $data['vat_rate'], 3, '.', ''), '0'), '.');

            return 'VAT @ '.$rate.'%: '.$data['currency'].' '.$this->formatAmount($data['vat_amount']);
        }

        return 'VAT Treatment: '.ucwords(str_replace('_', ' ', $data['vat_treatment']));
    }

    private function formatAmount(string $amount): string
    {
        return number_format((float) $amount, 2);
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function secret(): string
    {
        $secret = config('app.quotation_verification_secret')
            ?: config('app.key')
            ?: env('APP_KEY')
            ?: 'sms-environmental-alliance-local-verification-secret';

        return str_starts_with($secret, 'base64:')
            ? base64_decode(substr($secret, 7), true) ?: $secret
            : $secret;
    }
}
