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

    // Deterministic fields the local parser may fill when the AI left them blank.
    // Semantic fields (company, contact_person, designation, parent, address) are
    // NOT supplemented — a deliberate AI null there is respected.
    private const LOCAL_SUPPLEMENT_FIELDS = [
        'email',
        'phone',
        'website',
        'postal_code',
        'country',
        'city',
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
        $aiAttempted = false;

        foreach ($this->providers as $provider) {
            if (! $provider->isConfigured()) {
                continue;
            }

            $aiAttempted = true;

            try {
                $ai = $this->sanitize($provider->extract($text));
                $merged = $this->mergeAiWithLocal($ai, $local);

                if ($this->hasMeaningfulData($merged)) {
                    return $this->result($merged, 'ai', $provider->name());
                }
            } catch (\Throwable) {
                continue;
            }
        }

        if ($this->hasMeaningfulData($local)) {
            return $this->result($local, 'local', null, $aiAttempted);
        }

        return $this->result(self::blankData(), 'none', null, $aiAttempted);
    }

    /**
     * Build the response with honest provenance + review metadata. The message
     * never claims "AI detected" when only the local parser ran.
     */
    private function result(array $data, string $source, ?string $provider, bool $aiAttempted = false): array
    {
        $detected = array_values(array_filter(self::FIELDS, fn ($f) => filled($data[$f] ?? null)));
        $missing = array_values(array_diff(self::FIELDS, $detected));
        $warnings = [];

        if ($source === 'none') {
            $message = 'No details could be identified automatically. Please enter them manually.';
        } elseif ($source === 'local') {
            $message = count($detected).' field(s) detected by local extraction'
                .($aiAttempted ? ' (AI was unavailable)' : '').'. Please review before saving.';
            if ($aiAttempted) {
                $warnings[] = 'AI extraction was unavailable, so details were detected locally. Please review carefully.';
            }
        } else {
            $message = count($detected).' field(s) detected. Please review before saving.';
        }

        // Field-level review hints.
        if ($source !== 'none') {
            if (blank($data['company_name'] ?? null)) {
                $warnings[] = filled($data['parent_company'] ?? null)
                    ? 'Only a parent/group company was found — the specific company name needs your review.'
                    : 'Company name was not found — please add it.';
            }
            if (blank($data['phone'] ?? null)) {
                $warnings[] = 'Phone number was not detected.';
            }
        }

        return [
            'data' => $data,
            'source' => $source,
            'provider' => $provider,
            'message' => $message,
            'fields_detected' => $detected,
            'fields_missing' => $missing,
            'warnings' => $warnings,
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
