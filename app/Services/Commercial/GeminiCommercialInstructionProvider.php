<?php

namespace App\Services\Commercial;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Gemini-backed commercial-instruction extractor. Reuses the app's existing
 * services.ai.gemini configuration. Returns structured JSON only — no IDs, no
 * official totals. Logs metadata only (never the pasted instruction or payloads).
 */
class GeminiCommercialInstructionProvider implements CommercialInstructionProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.ai.gemini.key')) && filled(config('services.ai.gemini.model'));
    }

    public function extract(string $text): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Gemini is not configured.');
        }

        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => config('services.ai.gemini.key'),
                'Content-Type' => 'application/json',
            ])
                ->timeout((int) config('services.ai.timeout', 20))
                ->post(rtrim(config('services.ai.gemini.base_url'), '/').'/v1beta/models/'.rawurlencode(config('services.ai.gemini.model')).':generateContent', [
                    'contents' => [['parts' => [['text' => $this->prompt($text)]]]],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'response_mime_type' => 'application/json',
                        'response_schema' => CommercialInstructionExtractor::schema(),
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $this->logResult(false, null, $startedAt);
            throw new RuntimeException('Gemini request failed.', 0, $exception);
        }

        $this->logResult($response->successful(), $response->status(), $startedAt);

        if (! $response->successful()) {
            throw new RuntimeException('Gemini request failed.');
        }

        $content = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Gemini returned no structured text.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini returned invalid JSON.');
        }

        return $decoded;
    }

    private function prompt(string $text): string
    {
        $prompt = <<<'PROMPT'
You extract structured fields from short commercial instructions (WhatsApp/email)
used to prepare quotations and proforma invoices for a certification/testing firm.

Rules:
- Extract only what is present. Never invent. Use null / empty arrays when unsure.
- Do NOT calculate totals, VAT or currency conversion. Return the numbers as given.
- document_type: normalize to "quotation" or "proforma_invoice" (PI/proforma/invoice -> proforma_invoice; quote -> quotation). Null if unclear.
- charge_presentation: one of "consolidated", "component_breakdown", "itemized", or null.
  Multiple separately priced fees -> itemized. Several components with one total -> component_breakdown. Single fee -> consolidated.
- selected_standards: certification standards (e.g. "ISO 9001", "ISO 14001", "GOTS", "BSCI").
- selected_packages / selected_services: named service packages (e.g. "Environmental Parameter Testing", "Environmental Impact Assessment", "Energy Audit").
- charge_particulars: fee line wordings (e.g. "Audit Fee", "Travel Cost", "Administration Fee").
- itemized_rows: [{description, amount}] when separate prices are given per line.
- consolidated_amount: a single total when there is one overall amount.
- currency: normalize to a code (BDT, USD, EUR...). conversion_rate: numeric only (e.g. 124).
- entity_name, client_name, site_name, contact_person, designation, email, cc, bank_name, reference, notes as present.

Return JSON only.

Extract this instruction:
PROMPT;

        return $prompt."\n".$text;
    }

    private function logResult(bool $success, ?int $status, float $startedAt): void
    {
        Log::info('Commercial draft AI extraction completed.', [
            'provider' => $this->name(),
            'success' => $success,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
