<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiClientInformationProvider implements ClientInformationProvider
{
    public function name(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.ai.gemini.key')) && filled(config('services.ai.gemini.model'));
    }

    /** HTTP statuses worth retrying — transient rate-limit / overload / gateway. */
    private const RETRYABLE = [429, 500, 502, 503, 504];

    public function extract(string $text): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Gemini is not configured.');
        }

        $attempts = max(1, (int) config('services.ai.retries', 2) + 1);
        $lastStatus = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $startedAt = microtime(true);

            try {
                $response = $this->send($text);
            } catch (ConnectionException $exception) {
                $this->logResult(false, null, $startedAt, $attempt);
                if ($attempt < $attempts) {
                    $this->backoff($attempt);

                    continue; // transient network failure — retry
                }
                throw new RuntimeException('Gemini request failed (connection).', 0, $exception);
            }

            $this->logResult($response->successful(), $response->status(), $startedAt, $attempt);

            if ($response->successful()) {
                return $this->decode($response);
            }

            $lastStatus = $response->status();

            // Retry transient overload/rate-limit; fail fast on 4xx client errors.
            if (in_array($lastStatus, self::RETRYABLE, true) && $attempt < $attempts) {
                $this->backoff($attempt);

                continue;
            }

            break;
        }

        throw new RuntimeException('Gemini request failed (HTTP '.($lastStatus ?? 'unknown').').');
    }

    private function send(string $text)
    {
        return Http::withHeaders([
            'x-goog-api-key' => config('services.ai.gemini.key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout((int) config('services.ai.timeout', 20))
            ->post(rtrim(config('services.ai.gemini.base_url'), '/').'/v1beta/models/'.rawurlencode(config('services.ai.gemini.model')).':generateContent', [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $this->prompt($text)],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'response_mime_type' => 'application/json',
                    'response_schema' => $this->schema(),
                ],
            ]);
    }

    private function decode($response): array
    {
        $content = $response->json('candidates.0.content.parts.0.text')
            ?? $response->json('output_text')
            ?? $response->json('output.0.content.0.text')
            ?? null;

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('Gemini returned no structured text.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Gemini returned invalid JSON.');
        }

        return $decoded;
    }

    private function backoff(int $attempt): void
    {
        $base = (int) config('services.ai.retry_delay_ms', 500);
        if ($base <= 0 || app()->runningUnitTests()) {
            return; // no real sleeping in tests
        }
        usleep(min(4000, $base * $attempt) * 1000); // linear backoff, capped at 4s
    }

    private function prompt(string $text): string
    {
        $prompt = <<<'PROMPT'
You are an information extraction engine for SMS Environmental Alliance.

Extract client information from unstructured business text copied from WhatsApp, email, documents, or messages.

Rules:
- Never invent information. Return null when uncertain.
- Read information by MEANING and LABELS, not by line position. Input may be in any order, single or multiline, from WhatsApp, an email signature, a business card or a copied document.
- A field label is never a value. Never output "Designation", "Mobile", "Group", "Address", "Contact Person" (or similar labels) as a field value; strip the label and keep only the value after it.
- "Group:" (or a value ending in "Group"/"Holdings") is the parent/group company, never the company name. If only a group is given, company_name is null.
- Distinguish the specific company name from the parent/group company.
- Distinguish a person name from a designation. Keep Bangladeshi name parts like "Md." as part of the name.
- Merge multiline addresses; stop at the next recognised label. Do not put a phone, email or website into the address.
- Extract phone exactly as written, keeping spaces/plus (e.g. "+880 1318 583378"); handle local 01XXXXXXXXX and international +880 formats.
- Do not derive a website from an email domain unless a website is explicitly present.
- Identify the country only when clearly supported by the address.
- Return structured JSON only.

Fields:
company_name
parent_company
contact_person
designation
department
email
phone
website
address
city
postal_code
country

Example A input:
UNI Garments Limited
80 Bayazid Bostami Rd, Chattogram 4210
sohel@rdmapparels.com
Mr. Sohel- Compliance Manager

Example A output:
{"company_name":"UNI Garments Limited","parent_company":null,"contact_person":"Sohel","designation":"Compliance Manager","department":null,"email":"sohel@rdmapparels.com","phone":null,"website":null,"address":"80 Bayazid Bostami Rd, Chattogram 4210","city":"Chattogram","postal_code":"4210","country":"Bangladesh"}

Example B input:
Masud Hossain Khan
Chief Executive Officer
Zhaofeng Gelatin Ltd.
Sutipara, Dhamrai, Savar, Dhaka, Bangladesh
masud96@gmail.com

Example B output:
{"company_name":"Zhaofeng Gelatin Ltd.","parent_company":null,"contact_person":"Masud Hossain Khan","designation":"Chief Executive Officer","department":null,"email":"masud96@gmail.com","phone":null,"website":null,"address":"Sutipara, Dhamrai, Savar, Dhaka, Bangladesh","city":"Dhaka","postal_code":null,"country":"Bangladesh"}

Example C input:
K.C INDUSTRIAL PARK - NIPA GROUP
K.C Bottom & Shirt Wear Company.
E-mail: compliance@kcipbd.com
Ratuti,Katchkura, Uttarkhan, Dhaka-1230

Example C output:
{"company_name":"K.C Bottom & Shirt Wear Company","parent_company":"K.C INDUSTRIAL PARK - NIPA GROUP","contact_person":null,"designation":null,"department":null,"email":"compliance@kcipbd.com","phone":null,"website":null,"address":"Ratuti, Katchkura, Uttarkhan, Dhaka-1230","city":"Dhaka","postal_code":"1230","country":null}

Example D input (labelled, group only, no company name):
Contact Person: Md. Saidul Islam Babul
Designation: Manager - HR & Compliance
Address:
Rampur, Trishal-Balipara Road,
Trishal-2220, Mymensingh, Bangladesh
Mobile: +880 1318 583378
Email: saidul.islam@wendlerinside.com
Website: www.wendlerinside.com
Group: Wendler Interlining Group

Example D output:
{"company_name":null,"parent_company":"Wendler Interlining Group","contact_person":"Md. Saidul Islam Babul","designation":"Manager - HR & Compliance","department":null,"email":"saidul.islam@wendlerinside.com","phone":"+880 1318 583378","website":"www.wendlerinside.com","address":"Rampur, Trishal-Balipara Road","city":"Trishal, Mymensingh","postal_code":"2220","country":"Bangladesh"}

Extract this input:
PROMPT;

        return $prompt."\n".$text;
    }

    private function schema(): array
    {
        $properties = [];

        foreach (ClientInformationExtractor::FIELDS as $field) {
            $properties[$field] = [
                'type' => 'string',
                'nullable' => true,
                'description' => str_replace('_', ' ', $field),
            ];
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => ClientInformationExtractor::FIELDS,
        ];
    }

    private function logResult(bool $success, ?int $status, float $startedAt, int $attempt = 1): void
    {
        // Metadata only — never the pasted contact text or the AI response body.
        Log::info('Smart Paste AI extraction attempt.', [
            'provider' => $this->name(),
            'success' => $success,
            'status' => $status,
            'attempt' => $attempt,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
