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
        $lines = collect(explode("\n", $normalized))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $normalized, $match)) {
            $data['email'] = $match[0];
        }

        $withoutEmails = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', ' ', $normalized);
        if (preg_match('/\b(?:https?:\/\/)?(?:www\.)[a-z0-9][a-z0-9\-]*(?:\.[a-z0-9][a-z0-9\-]*)+\b/i', $withoutEmails, $match)) {
            $data['website'] = $match[0];
        }

        if (preg_match('/(?:\+?88)?01[3-9]\d{8}\b/', $normalized, $match)) {
            $data['phone'] = $match[0];
        }

        if (preg_match('/\b(\d{4})\b/', $normalized, $match)) {
            $data['postal_code'] = $match[1];
        }

        if ($city = $this->detectCity($normalized)) {
            $data['city'] = $city;
        }

        if (preg_match('/\bBangladesh\b/i', $normalized)) {
            $data['country'] = 'Bangladesh';
        }

        $semantic = $this->extractFromLines($lines, $data['email']);

        return array_replace($data, array_filter($semantic, fn ($value) => filled($value)));
    }

    private function extractFromLines($lines, ?string $email): array
    {
        $data = $this->blankData();
        $available = $lines
            ->reject(fn (string $line) => $email && str_contains($line, $email))
            ->map(fn (string $line) => preg_replace('/^(?:E-?mail|Email|To)\s*:\s*/i', '', $line))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();

        foreach ($available as $index => $line) {
            if ($this->looksLikeAddress($line)) {
                $data['address'] = $line;
                continue;
            }

            if ($this->looksLikeCompany($line)) {
                if ($data['company_name'] && ! $data['parent_company']) {
                    $data['parent_company'] = $data['company_name'];
                    $data['company_name'] = $line;
                    continue;
                }

                if (! $data['company_name']) {
                    $data['company_name'] = $line;
                }

                continue;
            }

            if ($person = $this->personDesignationFromLine($line)) {
                $data['contact_person'] = $person['contact_person'];
                $data['designation'] = $person['designation'];
                continue;
            }

            if ($this->looksLikeDesignation($line) && ! $data['designation']) {
                $data['designation'] = $line;

                if (! $data['contact_person'] && $index > 0) {
                    $previous = $available[$index - 1];
                    if (! $this->looksLikeCompany($previous) && ! $this->looksLikeAddress($previous)) {
                        $data['contact_person'] = $this->normalizePersonName($previous);
                    }
                }
            }
        }

        if (! $data['company_name']) {
            $companyLine = $available->first(fn (string $line) => ! $this->looksLikeAddress($line)
                && ! $this->looksLikeDesignation($line)
                && ! $this->personDesignationFromLine($line));
            $data['company_name'] = $companyLine ?: null;
        }

        if (! $data['contact_person']) {
            $personLine = $available->first(fn (string $line) => ! $this->looksLikeAddress($line)
                && ! $this->looksLikeCompany($line)
                && ! $this->looksLikeDesignation($line));

            if ($personLine && $personLine !== $data['company_name']) {
                $data['contact_person'] = $this->normalizePersonName($personLine);
            }
        }

        return $data;
    }

    private function personDesignationFromLine(string $line): ?array
    {
        if (! preg_match('/^(?:Mr\.?|Ms\.?|Mrs\.?)?\s*([A-Za-z .]+?)\s*[-:]\s*(.+)$/i', $line, $match)) {
            return null;
        }

        if (! $this->looksLikeDesignation($match[2])) {
            return null;
        }

        return [
            'contact_person' => $this->normalizePersonName($match[1]),
            'designation' => trim($match[2]),
        ];
    }

    private function normalizePersonName(string $name): string
    {
        return trim(preg_replace('/^(?:Mr\.?|Ms\.?|Mrs\.?)\s+/i', '', trim($name)));
    }

    private function looksLikeCompany(string $line): bool
    {
        return (bool) preg_match('/\b(?:Limited|Ltd\.?|Company|Garments|Industries|Industrial|Factory|Group|Park|Corporation|Corp\.?|Apparels|Textiles)\b/i', $line);
    }

    private function looksLikeDesignation(string $line): bool
    {
        return (bool) preg_match('/\b(?:Manager|Director|Officer|Executive|CEO|Chief|Compliance|Managing|Engineer|Coordinator|In[- ]Charge|Head)\b/i', $line);
    }

    private function looksLikeAddress(string $line): bool
    {
        return (bool) preg_match('/\d|Road|Rd\.?|Street|St\.?|Avenue|Ave\.?|Holding|House|Bazar|PO:|Dhaka|Chattogram|Chittagong|Gazipur|Savar|Dhamrai|Bangladesh/i', $line)
            && ! filter_var($line, FILTER_VALIDATE_EMAIL);
    }

    private function detectCity(string $text): ?string
    {
        foreach (['Chattogram', 'Chittagong', 'Dhaka', 'Gazipur', 'Savar'] as $city) {
            if (preg_match('/\b'.preg_quote($city, '/').'\b/i', $text)) {
                return $city === 'Chittagong' ? 'Chattogram' : $city;
            }
        }

        return null;
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
