<?php

namespace Tests\Unit;

use App\Services\ClientInformationExtractor;
use App\Services\ClientInformationProvider;
use App\Services\GeminiClientInformationProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientInformationExtractorTest extends TestCase
{
    public function test_local_extraction_finds_simple_fields_without_ai(): void
    {
        Config::set('services.ai.provider', null);

        $data = (new ClientInformationExtractor)->extract('Email: compliance@kcipbd.com Ratuti, Dhaka-1230 Bangladesh');

        $this->assertSame('compliance@kcipbd.com', $data['email']);
        $this->assertSame('Dhaka', $data['city']);
        $this->assertSame('1230', $data['postal_code']);
        $this->assertSame('Bangladesh', $data['country']);
        $this->assertNull($data['company_name']);
    }

    public function test_gemini_configuration_controls_provider_availability(): void
    {
        Config::set('services.ai.gemini.key', null);
        Config::set('services.ai.gemini.model', 'gemini-test');
        $this->assertFalse((new GeminiClientInformationProvider)->isConfigured());

        Config::set('services.ai.gemini.key', 'test-key');
        $this->assertTrue((new GeminiClientInformationProvider)->isConfigured());
    }

    public function test_gemini_structured_response_is_decoded(): void
    {
        Config::set('services.ai.gemini.key', 'test-key');
        Config::set('services.ai.gemini.model', 'gemini-test');
        Config::set('services.ai.gemini.base_url', 'https://generativelanguage.googleapis.com');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'company_name' => 'UNI Garments Limited',
                                'contact_person' => 'Sohel',
                                'designation' => 'Compliance Manager',
                                'email' => 'sohel@rdmapparels.com',
                                'address' => '80 Bayazid Bostami Rd, Chattogram 4210',
                                'city' => 'Chattogram',
                                'postal_code' => '4210',
                                'country' => 'Bangladesh',
                            ]),
                        ]],
                    ],
                ]],
            ]),
        ]);

        $data = (new GeminiClientInformationProvider)->extract('client text');

        $this->assertSame('UNI Garments Limited', $data['company_name']);
        $this->assertSame('Sohel', $data['contact_person']);
        Http::assertSent(fn ($request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent'
            && $request->hasHeader('x-goog-api-key', 'test-key')
            && $request['generationConfig']['response_mime_type'] === 'application/json'
            && $request['generationConfig']['response_schema']['properties']['company_name']['nullable'] === true);
    }

    public function test_ai_is_primary_over_weak_local_semantics(): void
    {
        $extractor = new ClientInformationExtractor(providers: [
            new FakeProvider([
                'company_name' => 'UNI Garments Limited',
                'contact_person' => 'Sohel',
                'designation' => 'Compliance Manager',
                'address' => '80 Bayazid Bostami Rd, Chattogram 4210',
                'city' => 'Chattogram',
                'country' => 'Bangladesh',
            ]),
        ]);

        $result = $extractor->extractWithMetadata("UNI Garments Limited\n80 Bayazid Bostami Rd, Chattogram 4210\nsohel@rdmapparels.com\nMr. Sohel- Compliance Manager");

        $this->assertSame('ai', $result['source']);
        $this->assertSame('UNI Garments Limited', $result['data']['company_name']);
        $this->assertSame('Sohel', $result['data']['contact_person']);
    }

    public function test_local_obvious_fields_supplement_ai_blanks(): void
    {
        $extractor = new ClientInformationExtractor(providers: [
            new FakeProvider([
                'company_name' => 'UNI Garments Limited',
                'address' => '80 Bayazid Bostami Rd, Chattogram 4210',
            ]),
        ]);

        $data = $extractor->extract("UNI Garments Limited\n80 Bayazid Bostami Rd, Chattogram 4210\nsohel@rdmapparels.com");

        $this->assertSame('UNI Garments Limited', $data['company_name']);
        $this->assertSame('sohel@rdmapparels.com', $data['email']);
        $this->assertSame('4210', $data['postal_code']);
    }

    public function test_ai_null_fields_are_preserved_for_semantic_fields(): void
    {
        $extractor = new ClientInformationExtractor(providers: [
            new FakeProvider([
                'company_name' => 'Zhaofeng Gelatin Ltd.',
                'contact_person' => null,
                'designation' => null,
                'email' => 'masud96@gmail.com',
            ]),
        ]);

        $data = $extractor->extract("Masud Hossain Khan\nChief Executive Officer\nZhaofeng Gelatin Ltd.\nmasud96@gmail.com");

        $this->assertSame('Zhaofeng Gelatin Ltd.', $data['company_name']);
        $this->assertNull($data['contact_person']);
        $this->assertNull($data['designation']);
        $this->assertSame('masud96@gmail.com', $data['email']);
    }

    public function test_invalid_gemini_response_falls_back_to_local(): void
    {
        Config::set('services.ai.gemini.key', 'test-key');
        Config::set('services.ai.gemini.model', 'gemini-test');
        Config::set('services.ai.gemini.base_url', 'https://generativelanguage.googleapis.com');

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => [
                    'parts' => [['text' => 'not-json']],
                ],
            ]],
        ])]);

        $result = (new ClientInformationExtractor(providers: [new GeminiClientInformationProvider]))
            ->extractWithMetadata('client@example.com');

        $this->assertSame('local', $result['source']);
        $this->assertSame('client@example.com', $result['data']['email']);
    }

    public function test_timeout_falls_back_to_local(): void
    {
        $provider = new class implements ClientInformationProvider {
            public function name(): string { return 'gemini'; }
            public function isConfigured(): bool { return true; }
            public function extract(string $text): array { throw new ConnectionException('timeout'); }
        };

        $result = (new ClientInformationExtractor(providers: [$provider]))->extractWithMetadata('client@example.com');

        $this->assertSame('local', $result['source']);
        $this->assertSame('client@example.com', $result['data']['email']);
    }

    public function test_rate_limit_or_error_falls_back_to_local(): void
    {
        Config::set('services.ai.gemini.key', 'test-key');
        Config::set('services.ai.gemini.model', 'gemini-test');
        Config::set('services.ai.gemini.base_url', 'https://generativelanguage.googleapis.com');

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 429)]);

        $result = (new ClientInformationExtractor(providers: [new GeminiClientInformationProvider]))
            ->extractWithMetadata('client@example.com');

        $this->assertSame('local', $result['source']);
        $this->assertSame('client@example.com', $result['data']['email']);
    }

    public function test_no_ai_configured_returns_none_when_local_is_empty(): void
    {
        Config::set('services.ai.provider', null);

        $result = (new ClientInformationExtractor)->extractWithMetadata('hello there');

        $this->assertSame('none', $result['source']);
        $this->assertSame('Information could not be detected automatically. Please enter the client details manually.', $result['message']);
    }
}

class FakeProvider implements ClientInformationProvider
{
    public function __construct(private array $data)
    {
    }

    public function name(): string
    {
        return 'fake-ai';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function extract(string $text): array
    {
        return array_replace(ClientInformationExtractor::blankData(), $this->data);
    }
}
