<?php

namespace App\Services\Commercial;

/**
 * Orchestrates commercial-instruction extraction: runs the configured AI provider
 * (Gemini) and returns a normalized, strictly-shaped array plus lightweight
 * metadata. Never trusts the model for IDs or calculations — that is the
 * resolver's job. Providers are injectable so tests can supply a fake.
 */
class CommercialInstructionExtractor
{
    /** Scalar fields the model may return (arrays handled separately). */
    public const SCALAR_FIELDS = [
        'entity_name', 'document_type', 'client_name', 'site_name', 'site_address',
        'contact_person', 'designation', 'email', 'cc', 'service_category',
        'charge_presentation', 'currency', 'conversion_rate', 'consolidated_amount',
        'bank_name', 'reference', 'notes',
    ];

    public const LIST_FIELDS = [
        'selected_services', 'selected_standards', 'selected_packages', 'charge_particulars',
    ];

    /** @var array<int, CommercialInstructionProvider> */
    private array $providers;

    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?? $this->configuredProviders();
    }

    /**
     * @return array{ok:bool, data:array, provider:?string, message:string}
     */
    public function extract(string $text): array
    {
        foreach ($this->providers as $provider) {
            if (! $provider->isConfigured()) {
                continue;
            }

            try {
                return [
                    'ok' => true,
                    'data' => $this->normalize($provider->extract($text)),
                    'provider' => $provider->name(),
                    'message' => 'Draft detected. Please review every field before applying.',
                ];
            } catch (\Throwable) {
                // Fall through — never surface raw payloads; the caller keeps the text.
                continue;
            }
        }

        return [
            'ok' => false,
            'data' => self::blank(),
            'provider' => null,
            'message' => 'Unable to analyze automatically. Please retry or prepare the document manually.',
        ];
    }

    public static function blank(): array
    {
        $data = array_fill_keys(self::SCALAR_FIELDS, null);

        foreach (self::LIST_FIELDS as $field) {
            $data[$field] = [];
        }

        $data['itemized_rows'] = [];

        return $data;
    }

    /** Coerce a raw model payload into the strict shape, trimming everything. */
    public function normalize(array $raw): array
    {
        $data = self::blank();

        foreach (self::SCALAR_FIELDS as $field) {
            $value = $raw[$field] ?? null;
            $data[$field] = is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
        }

        foreach (self::LIST_FIELDS as $field) {
            $data[$field] = collect($raw[$field] ?? [])
                ->flatten()
                ->map(fn ($v) => is_scalar($v) ? trim((string) $v) : '')
                ->filter()
                ->values()
                ->all();
        }

        $data['itemized_rows'] = collect($raw['itemized_rows'] ?? [])
            ->map(fn ($row) => is_array($row) ? [
                'description' => trim((string) ($row['description'] ?? '')),
                'amount' => $this->number($row['amount'] ?? null),
            ] : null)
            ->filter(fn ($row) => $row && $row['description'] !== '')
            ->values()
            ->all();

        return $data;
    }

    /** Parse a loose numeric string ("1,200", "USD 1200", "30k") to a float or null. */
    public function number($value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = strtolower(trim($value));
        $multiplier = 1;

        if (preg_match('/^([\d.,]+)\s*k$/', $text, $m)) {
            $multiplier = 1000;
            $text = $m[1];
        }

        $clean = preg_replace('/[^0-9.]/', '', str_replace(',', '', $text));

        return $clean === '' || $clean === '.' ? null : round((float) $clean * $multiplier, 2);
    }

    /** JSON schema for the model (kept small; masters are resolved locally). */
    public static function schema(): array
    {
        $props = [];
        foreach (self::SCALAR_FIELDS as $field) {
            $props[$field] = ['type' => 'string', 'nullable' => true];
        }
        foreach (self::LIST_FIELDS as $field) {
            $props[$field] = ['type' => 'array', 'items' => ['type' => 'string']];
        }
        $props['itemized_rows'] = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => ['description' => ['type' => 'string'], 'amount' => ['type' => 'string', 'nullable' => true]],
            ],
        ];

        return ['type' => 'object', 'properties' => $props];
    }

    private function configuredProviders(): array
    {
        return match (config('services.ai.provider')) {
            'gemini' => [new GeminiCommercialInstructionProvider],
            default => [],
        };
    }
}
