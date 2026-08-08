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
                ->post(rtrim(config('services.ai.gemini.base_url'), '/').'/v1beta/interactions', [
                    'model' => config('services.ai.gemini.model'),
                    'input' => $this->prompt($text),
                    'response_format' => [
                        'type' => 'text',
                        'mime_type' => 'application/json',
                        'schema' => ClientInformationExtractor::schema(),
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

        $content = $response->json('output_text')
            ?? $response->json('output.0.content.0.text')
            ?? $response->json('candidates.0.content.parts.0.text')
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

    private function prompt(string $text): string
    {
        $prompt = <<<'PROMPT'
You are an information extraction engine for SMS Environmental Alliance.

Extract client information from unstructured business text copied from WhatsApp, email, documents, or messages.

Rules:
- Never invent information.
- Preserve company and person spelling.
- Return null when uncertain.
- Distinguish company name from parent/group company.
- Distinguish person name from designation.
- Merge multiline addresses.
- Extract valid email and phone.
- Do not derive a website from an email domain unless a website is explicitly present.
- Identify Bangladesh only when clearly supported by the address.
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

Extract this input:
PROMPT;

        return $prompt."\n".$text;
    }

    private function logResult(bool $success, ?int $status, float $startedAt): void
    {
        Log::info('Smart Paste AI extraction completed.', [
            'provider' => $this->name(),
            'success' => $success,
            'status' => $status,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
