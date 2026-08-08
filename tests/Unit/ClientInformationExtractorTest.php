<?php

namespace Tests\Unit;

use App\Services\ClientInformationExtractor;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
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

    public function test_invalid_ai_response_throws_clean_exception(): void
    {
        Config::set('services.ai.provider', 'openai');
        Config::set('services.ai.key', 'test-key');
        Config::set('services.ai.model', 'test-model');
        Http::fake(['api.openai.com/*' => Http::response([
            'output' => [[
                'content' => [['text' => 'not json']],
            ]],
        ], 200)]);

        $this->expectException(RuntimeException::class);

        (new ClientInformationExtractor)->extract('Client text');
    }

    public function test_api_failure_throws_clean_exception(): void
    {
        Config::set('services.ai.provider', 'openai');
        Config::set('services.ai.key', 'test-key');
        Config::set('services.ai.model', 'test-model');
        Http::fake(['api.openai.com/*' => Http::response([], 500)]);

        $this->expectException(RuntimeException::class);

        (new ClientInformationExtractor)->extract('Client text');
    }
}
