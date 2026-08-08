<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClientInformationExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartPasteClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_extraction_endpoint_requires_authorization(): void
    {
        $this->postJson(route('clients.smart-paste'), ['raw_text' => 'ABC Ltd'])
            ->assertRedirect(route('login'));
    }

    public function test_valid_structured_extraction_returns_data(): void
    {
        $this->mockExtractor([
            'company_name' => 'Zhaofeng Gelatin Ltd.',
            'contact_person' => 'Masud Hossain Khan',
            'designation' => 'Chief Executive Officer',
            'email' => 'masud96@gmail.com',
            'address' => 'Sutipara, Dhamrai, Savar, Dhaka, Bangladesh',
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('clients.smart-paste'), ['raw_text' => 'rough text'])
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Zhaofeng Gelatin Ltd.')
            ->assertJsonPath('data.email', 'masud96@gmail.com')
            ->assertJsonPath('duplicates', []);
    }

    public function test_uni_garments_endpoint_returns_javascript_response_contract(): void
    {
        config(['services.ai.provider' => null]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('clients.smart-paste'), [
                'raw_text' => "UNI Garments Limited\n80 Bayazid Bostami Rd, Chattogram 4210\nsohel@rdmapparels.com\nMr. Sohel- Compliance Manager",
            ])
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
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
                ],
                'duplicates',
            ])
            ->assertJsonPath('data.company_name', 'UNI Garments Limited')
            ->assertJsonPath('data.contact_person', 'Sohel')
            ->assertJsonPath('data.designation', 'Compliance Manager')
            ->assertJsonPath('data.email', 'sohel@rdmapparels.com')
            ->assertJsonPath('data.address', '80 Bayazid Bostami Rd, Chattogram 4210')
            ->assertJsonPath('data.city', 'Chattogram')
            ->assertJsonPath('data.postal_code', '4210');
    }

    public function test_missing_fields_remain_blank(): void
    {
        $this->mockExtractor([
            'company_name' => 'REEYAN KNIT WEAR LIMITED',
            'address' => 'Gazipur, Bangladesh',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('clients.smart-paste'), ['raw_text' => 'rough text'])
            ->assertOk()
            ->assertJsonPath('data.contact_person', null)
            ->assertJsonPath('data.phone', null);
    }

    public function test_extraction_failure_returns_manual_fallback_data(): void
    {
        $this->app->instance(ClientInformationExtractor::class, new class extends ClientInformationExtractor {
            public function extract(string $text): array
            {
                throw new \RuntimeException('failed');
            }

            public function localExtract(string $text): array
            {
                return array_replace(array_fill_keys(self::FIELDS, null), ['email' => 'client@example.com']);
            }
        });

        $this->actingAs(User::factory()->create())
            ->postJson(route('clients.smart-paste'), ['raw_text' => 'client@example.com'])
            ->assertUnprocessable()
            ->assertJsonPath('data.email', 'client@example.com');
    }

    public function test_duplicate_client_warning_on_detection(): void
    {
        Client::query()->create([
            'company_name' => 'K.C Bottom & Shirt Wear Company',
            'address' => 'Dhaka',
        ]);
        $this->mockExtractor([
            'company_name' => 'K.C Bottom & Shirt Wear Company.',
            'address' => 'Dhaka',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('clients.smart-paste'), ['raw_text' => 'rough text'])
            ->assertOk()
            ->assertJsonPath('duplicates.0.company_name', 'K.C Bottom & Shirt Wear Company');
    }

    public function test_saving_detected_client_and_duplicate_create_anyway(): void
    {
        $user = User::factory()->create();
        Client::query()->create([
            'company_name' => 'ABC Textiles Ltd.',
            'email' => 'abc@example.com',
            'address' => 'Dhaka',
        ]);

        $payload = [
            'company_name' => 'ABC Textiles Ltd.',
            'email' => 'abc@example.com',
            'address' => 'Dhaka, Bangladesh',
        ];

        $this->actingAs($user)->postJson(route('clients.smart-store'), $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Possible existing client found.');

        $this->actingAs($user)->postJson(route('clients.smart-store'), [...$payload, 'create_anyway' => true])
            ->assertCreated()
            ->assertJsonPath('client.label', 'ABC Textiles Ltd.');

        $this->assertSame(2, Client::query()->count());
    }

    public function test_inline_client_can_be_selected_for_quotation_without_losing_document_data(): void
    {
        $user = User::factory()->create();
        [$service, $bank] = $this->setupDocumentData();

        $clientId = $this->actingAs($user)->postJson(route('clients.smart-store'), [
            'company_name' => 'INLINE CLIENT LIMITED',
            'address' => 'Dhaka, Bangladesh',
        ])->assertCreated()->json('client.id');

        $this->actingAs($user)->post(route('quotations.store'), [
            'client_id' => $clientId,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'items' => [[
                'service_id' => $service->id,
                'description' => '',
                'unit' => '',
                'quantity' => 2,
                'unit_rate' => 2500,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('quotations', ['total' => 5000]);
        $this->assertDatabaseHas('clients', ['company_name' => 'INLINE CLIENT LIMITED']);
    }

    public function test_inline_client_can_be_selected_for_invoice_without_losing_document_data(): void
    {
        $user = User::factory()->create();
        [$service, $bank] = $this->setupDocumentData();

        $clientId = $this->actingAs($user)->postJson(route('clients.smart-store'), [
            'company_name' => 'INLINE INVOICE CLIENT LIMITED',
            'address' => 'Dhaka, Bangladesh',
        ])->assertCreated()->json('client.id');

        $this->actingAs($user)->post(route('proforma-invoices.store'), [
            'client_id' => $clientId,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'items' => [[
                'service_id' => $service->id,
                'description' => '',
                'unit' => '',
                'quantity' => 1,
                'unit_rate' => 7500,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('proforma_invoices', ['total' => 7500]);
        $this->assertDatabaseHas('clients', ['company_name' => 'INLINE INVOICE CLIENT LIMITED']);
    }

    private function mockExtractor(array $data): void
    {
        $this->app->instance(ClientInformationExtractor::class, new class($data) extends ClientInformationExtractor {
            public function __construct(private array $data)
            {
            }

            public function extract(string $text): array
            {
                return array_replace(array_fill_keys(self::FIELDS, null), $this->data);
            }
        });
    }

    private function setupDocumentData(): array
    {
        Setting::query()->create([
            'organization_name' => 'SMS Environmental Alliance',
            'default_currency' => 'BDT',
            'currency_major_name' => 'Taka',
            'currency_minor_name' => 'Paisa',
            'prepared_by_name' => 'Prepared Person',
            'prepared_by_designation' => 'Officer',
            'default_payment_terms' => 'Default terms.',
            'quotation_number_format' => 'SMSEA/QT/{YYYY}/{####}',
            'invoice_number_format' => 'SMSEA/PI/{YYYY}/{####}',
        ]);

        $service = Service::query()->create([
            'name' => 'Ambient Air Quality Test',
            'default_description' => 'Ambient Air Quality Test',
            'default_unit' => 'Point',
            'default_rate' => 2500,
            'is_active' => true,
        ]);

        $bank = BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Test Bank',
            'account_number' => '123456',
            'is_active' => true,
        ]);

        return [$service, $bank];
    }
}
