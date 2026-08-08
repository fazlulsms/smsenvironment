<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

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

    public function extract(string $text): array
    {
        $local = $this->localExtract($text);

        if (! $this->hasAiConfiguration()) {
            return $this->sanitize($local);
        }

        try {
            return $this->sanitize([
                ...$local,
                ...array_filter($this->extractWithAi($text), fn ($value) => filled($value)),
            ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Smart Paste extraction failed. You can still enter the client manually.', 0, $exception);
        }
    }

    public function localExtract(string $text): array
    {
        $data = $this->blankData();
        $normalized = trim(preg_replace("/\r\n?/", "\n", $text));

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $normalized, $match)) {
            $data['email'] = $match[0];
        }

        if (preg_match('/(?:https?:\/\/)?(?:www\.)?[a-z0-9][a-z0-9\-]*(?:\.[a-z0-9][a-z0-9\-]*)+\b/i', $normalized, $match)) {
            if (! str_contains($match[0], '@')) {
                $data['website'] = $match[0];
            }
        }

        if (preg_match('/(?:\+?88)?01[3-9]\d{8}\b/', $normalized, $match)) {
            $data['phone'] = $match[0];
        }

        if (preg_match('/(?:Dhaka|Bangladesh)?\s*[-, ]\s*(\d{4})\b/i', $normalized, $match)) {
            $data['postal_code'] = $match[1];
        }

        if (preg_match('/\bDhaka\b/i', $normalized)) {
            $data['city'] = 'Dhaka';
        }

        if (preg_match('/\bBangladesh\b/i', $normalized)) {
            $data['country'] = 'Bangladesh';
        }

        return $data;
    }

    private function extractWithAi(string $text): array
    {
        if (config('services.ai.provider') !== 'openai') {
            throw new RuntimeException('Unsupported AI provider.');
        }

        $response = Http::withToken(config('services.ai.key'))
            ->timeout(20)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.ai.model'),
                'input' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $text,
                    ],
                ],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'client_information',
                        'strict' => true,
                        'schema' => $this->schema(),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AI provider request failed.');
        }

        $content = $response->json('output.0.content.0.text')
            ?? $response->json('output_text')
            ?? null;

        if (! is_string($content)) {
            throw new RuntimeException('AI provider returned no structured text.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI provider returned invalid JSON.');
        }

        return $decoded;
    }

    private function sanitize(array $data): array
    {
        $clean = $this->blankData();

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

    private function blankData(): array
    {
        return array_fill_keys(self::FIELDS, null);
    }

    private function hasAiConfiguration(): bool
    {
        return config('services.ai.provider') && config('services.ai.key') && config('services.ai.model');
    }

    private function systemPrompt(): string
    {
        return 'Extract client information only. Return structured JSON only. Do not invent missing information. Preserve spelling where practical. Use null when uncertain. Do not summarize or explain.';
    }

    private function schema(): array
    {
        $properties = [];

        foreach (self::FIELDS as $field) {
            $properties[$field] = ['type' => ['string', 'null']];
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $properties,
            'required' => self::FIELDS,
        ];
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
}
