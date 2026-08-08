<?php

namespace App\Services;

use Illuminate\Support\Str;

class ClientInformationExtractor
{
    public const FIELDS = [
        'company_name',
        'parent_company',
        'contact_person',
        'designation',
        'department',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'postal_code',
        'country',
    ];

    private const LOCAL_SUPPLEMENT_FIELDS = [
        'email',
        'phone',
        'website',
        'postal_code',
    ];

    private LocalClientInformationExtractor $localExtractor;

    /** @var array<int, ClientInformationProvider> */
    private array $providers;

    public function __construct(?LocalClientInformationExtractor $localExtractor = null, ?array $providers = null)
    {
        $this->localExtractor = $localExtractor ?: new LocalClientInformationExtractor;
        $this->providers = $providers ?? $this->configuredProviders();
    }

    public function extract(string $text): array
    {
        return $this->extractWithMetadata($text)['data'];
    }

    public function extractWithMetadata(string $text): array
    {
        $local = $this->sanitize($this->localExtract($text));

        foreach ($this->providers as $provider) {
            if (! $provider->isConfigured()) {
                continue;
            }

            try {
                $ai = $this->sanitize($provider->extract($text));
                $merged = $this->mergeAiWithLocal($ai, $local);

                if ($this->hasMeaningfulData($merged)) {
                    return [
                        'data' => $merged,
                        'source' => 'ai',
                        'provider' => $provider->name(),
                        'message' => 'Information detected. Please review before saving.',
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($this->hasMeaningfulData($local)) {
            return [
                'data' => $local,
                'source' => 'local',
                'provider' => null,
                'message' => 'Some information was detected locally. Please review before saving.',
            ];
        }

        return [
            'data' => self::blankData(),
            'source' => 'none',
            'provider' => null,
            'message' => 'Information could not be detected automatically. Please enter the client details manually.',
        ];
    }

    public function localExtract(string $text): array
    {
        return $this->localExtractor->extract($text);
    }

    public static function blankData(): array
    {
        return array_fill_keys(self::FIELDS, null);
    }

    public static function schema(): array
    {
        $properties = [];

        foreach (self::FIELDS as $field) {
            $properties[$field] = [
                'type' => ['string', 'null'],
                'description' => str_replace('_', ' ', $field),
            ];
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
            'required' => self::FIELDS,
        ];
    }

    public static function systemPrompt(): string
    {
        return 'Extract client information only. Return structured JSON only. Do not invent missing information. Preserve spelling where practical. Use null when uncertain. Do not summarize or explain.';
    }

    public static function normalizeCompany(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        return Str::of($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
    }

    public function sanitize(array $data): array
    {
        $clean = self::blankData();

        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? null;
            $clean[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        if ($clean['email'] && ! filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
            $clean['email'] = null;
        }

        if ($clean['postal_code'] && ! preg_match('/^\d{4}$/', $clean['postal_code'])) {
            $clean['postal_code'] = null;
        }

        return $clean;
    }

    private function mergeAiWithLocal(array $ai, array $local): array
    {
        $merged = $ai;

        foreach (self::LOCAL_SUPPLEMENT_FIELDS as $field) {
            if (! filled($merged[$field] ?? null) && filled($local[$field] ?? null)) {
                $merged[$field] = $local[$field];
            }
        }

        return $merged;
    }

    private function hasMeaningfulData(array $data): bool
    {
        foreach (self::FIELDS as $field) {
            if (filled($data[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function configuredProviders(): array
    {
        return match (config('services.ai.provider')) {
            'gemini' => [new GeminiClientInformationProvider],
            'openai' => [new OpenAIClientInformationProvider],
            default => [],
        };
    }
}
