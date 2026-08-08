<?php

namespace App\Services;

use App\Models\Quotation;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QuotationVerificationService
{
    public const PAYLOAD_VERSION = 'SMSEA-QT-V1';

    public function apply(Quotation $quotation): Quotation
    {
        $signature = $this->signature($quotation);

        $quotation->forceFill([
            'verification_payload_version' => self::PAYLOAD_VERSION,
            'verification_signature' => $signature,
            'verification_id' => $this->verificationId($signature),
        ])->save();

        return $quotation;
    }

    public function ensure(Quotation $quotation): Quotation
    {
        if (
            $quotation->verification_payload_version === self::PAYLOAD_VERSION
            && filled($quotation->verification_id)
            && filled($quotation->verification_signature)
        ) {
            return $quotation;
        }

        return $this->apply($quotation);
    }

    public function canonicalData(Quotation $quotation): array
    {
        $quotation->loadMissing('items');
        $client = $quotation->client_snapshot ?: [];
        $settings = $quotation->settings_snapshot ?: [];

        return [
            'version' => self::PAYLOAD_VERSION,
            'reference' => $this->clean($quotation->number),
            'date' => optional($quotation->date)->format('Y-m-d') ?: $this->clean((string) $quotation->date),
            'client' => $this->clean($client['company_name'] ?? ''),
            'client_address' => $this->clean($client['address'] ?? ''),
            'services' => $this->serviceData($quotation),
            'currency' => $this->clean($settings['default_currency'] ?? 'BDT'),
            'net_amount' => $this->money((float) $quotation->subtotal + (float) $quotation->adjustment),
            'vat_treatment' => $this->clean($quotation->vat_treatment ?: 'exclusive'),
            'vat_rate' => number_format((float) ($quotation->vat_rate ?? 0), 3, '.', ''),
            'vat_amount' => $this->money((float) ($quotation->vat_amount ?? 0)),
            'total_amount' => $this->money((float) $quotation->total),
        ];
    }

    public function signature(Quotation $quotation): string
    {
        return hash_hmac('sha256', $this->canonicalString($quotation), $this->secret());
    }

    public function verificationId(string $signature): string
    {
        return implode('-', str_split(strtoupper(substr($signature, 0, 16)), 4));
    }

    public function payloadText(Quotation $quotation): string
    {
        $quotation = $this->ensure($quotation);
        $data = $this->canonicalData($quotation);
        $client = $quotation->client_snapshot ?: [];
        $serviceLines = collect($data['services'])
            ->map(fn (array $service, int $index) => ($index + 1).'. '.$service['name'])
            ->implode("\n");

        $lines = [
            'SMS ENVIRONMENTAL ALLIANCE',
            'QUOTATION VERIFICATION',
            '',
            'Reference: '.$data['reference'],
            'Date: '.$data['date'],
            '',
            'Client: '.$data['client'],
            'Address: '.$data['client_address'],
            '',
            'Services:',
            $serviceLines,
            '',
            'Net Amount: '.$data['currency'].' '.$this->formatAmount($data['net_amount']),
            $this->vatLine($data),
            'Total Payable: '.$data['currency'].' '.$this->formatAmount($data['total_amount']),
            '',
            'Verification ID: '.$quotation->verification_id,
            'Version: '.self::PAYLOAD_VERSION,
            'Signature: '.$quotation->verification_signature,
        ];

        return collect($lines)->filter(fn ($line) => $line !== null)->implode("\n");
    }

    public function qrSvg(Quotation $quotation): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(
                180,
                4,
                null,
                null,
                Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(31, 111, 74))
            ),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString(
            $this->payloadText($quotation),
            'UTF-8',
            ErrorCorrectionLevel::M()
        );
    }

    public function qrDataUri(Quotation $quotation): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->qrSvg($quotation));
    }

    private function canonicalString(Quotation $quotation): string
    {
        return json_encode(
            $this->canonicalData($quotation),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    private function serviceData(Quotation $quotation): array
    {
        return $quotation->items
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($item) => [
                'name' => $this->serviceName((string) $item->description),
                'snapshot_description' => $this->clean((string) $item->description),
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

        $lower = strtolower($description);
        $knownNames = [
            'environmental impact assessment package',
            'environmental impact assessment',
            'environmental management plan',
            'environmental parameter assessment package',
            'environmental parameter assessment',
            'noise level assessment',
            'energy audit',
            'environmental and social impact assessment',
        ];

        foreach ($knownNames as $knownName) {
            if (str_contains($lower, $knownName)) {
                return str($knownName)->title()->replace('Emp', 'EMP')->toString();
            }
        }

        if (mb_strlen($description) > 90) {
            $sentence = preg_split('/[.;]/', $description)[0] ?? $description;

            return mb_strlen($sentence) > 90 ? mb_substr($sentence, 0, 87).'...' : $this->clean($sentence);
        }

        return $description ?: 'Service';
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
