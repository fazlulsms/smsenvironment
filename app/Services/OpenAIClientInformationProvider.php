<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAIClientInformationProvider implements ClientInformationProvider
{
    public function name(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.ai.openai.key')) && filled(config('services.ai.openai.model'));
    }

    public function extract(string $text): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('OpenAI is not configured.');
        }

        $startedAt = microtime(true);

        try {
            $response = Http::withToken(config('services.ai.openai.key'))
                ->timeout((int) config('services.ai.timeout', 20))
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.ai.openai.model'),
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => ClientInformationExtractor::systemPrompt(),
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
                            'schema' => ClientInformationExtractor::schema(),
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            $this->logResult(false, null, $startedAt);
            throw new RuntimeException('OpenAI request failed.', 0, $exception);
        }

        $this->logResult($response->successful(), $response->status(), $startedAt);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI request failed.');
        }

        $content = $response->json('output.0.content.0.text')
            ?? $response->json('output_text')
            ?? null;

        if (! is_string($content)) {
            throw new RuntimeException('OpenAI returned no structured text.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned invalid JSON.');
        }

        return $decoded;
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
