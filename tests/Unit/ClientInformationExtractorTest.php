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

    public function test_local_extraction_handles_uni_garments_whatsapp_text(): void
    {
        Config::set('services.ai.provider', null);

        $data = (new ClientInformationExtractor)->extract("UNI Garments Limited\n80 Bayazid Bostami Rd, Chattogram 4210\nsohel@rdmapparels.com\nMr. Sohel- Compliance Manager");

        $this->assertSame('UNI Garments Limited', $data['company_name']);
        $this->assertSame('Sohel', $data['contact_person']);
        $this->assertSame('Compliance Manager', $data['designation']);
        $this->assertSame('sohel@rdmapparels.com', $data['email']);
        $this->assertSame('80 Bayazid Bostami Rd, Chattogram 4210', $data['address']);
        $this->assertSame('Chattogram', $data['city']);
        $this->assertSame('4210', $data['postal_code']);
        $this->assertNull($data['country']);
        $this->assertNull($data['website']);
    }

    public function test_local_extraction_handles_person_designation_company_address_order(): void
    {
        Config::set('services.ai.provider', null);

        $data = (new ClientInformationExtractor)->extract("Masud Hossain Khan\nChief Executive Officer\nZhaofeng Gelatin Ltd.\nSutipara, Dhamrai, Savar, Dhaka, Bangladesh\nmasud96@gmail.com");

        $this->assertSame('Zhaofeng Gelatin Ltd.', $data['company_name']);
        $this->assertSame('Masud Hossain Khan', $data['contact_person']);
        $this->assertSame('Chief Executive Officer', $data['designation']);
        $this->assertSame('masud96@gmail.com', $data['email']);
        $this->assertSame('Sutipara, Dhamrai, Savar, Dhaka, Bangladesh', $data['address']);
        $this->assertSame('Dhaka', $data['city']);
        $this->assertSame('Bangladesh', $data['country']);
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
