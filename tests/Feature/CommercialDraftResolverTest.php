<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Services\Commercial\CommercialDraftResolver;
use App\Services\Commercial\CommercialInstructionExtractor;
use App\Services\Commercial\CommercialInstructionProvider;
use Database\Seeders\ChargeParticularSeeder;
use Database\Seeders\StandardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialDraftResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardSeeder::class);
        $this->seed(ChargeParticularSeeder::class);
    }

    /** A stand-in Gemini provider returning a canned extraction (no live calls). */
    private function fakeProvider(array $payload): CommercialInstructionProvider
    {
        return new class($payload) implements CommercialInstructionProvider
        {
            public function __construct(private array $payload) {}

            public function name(): string
            {
                return 'fake';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function extract(string $text): array
            {
                return $this->payload;
            }
        };
    }

    private function entityId(string $code): int
    {
        return BusinessEntity::query()->where('entity_code', $code)->value('id');
    }

    private function resolve(array $payload, string $entityCode): array
    {
        $extractor = new CommercialInstructionExtractor([$this->fakeProvider($payload)]);
        $result = $extractor->extract('irrelevant');
        $this->assertTrue($result['ok']);

        return app(CommercialDraftResolver::class)->resolve($result['data'], $this->entityId($entityCode));
    }

    public function test_eidikos_iso_itemized_resolves_with_entity_scoped_bank_and_calculations(): void
    {
        Client::query()->create(['company_name' => 'P.A. knit Composite Ltd.', 'address' => 'Bhaluka']);

        $draft = $this->resolve([
            'entity_name' => 'Eidikos', 'document_type' => 'PI', 'client_name' => 'P.A Knit',
            'selected_standards' => ['ISO 9001', 'ISO 14001'], 'charge_presentation' => 'itemized',
            'itemized_rows' => [
                ['description' => 'ISO 9001 Certification Fee', 'amount' => '1200'],
                ['description' => 'ISO 14001 Certification Fee', 'amount' => '1000'],
            ],
            'currency' => 'USD', 'conversion_rate' => '124', 'bank_name' => 'City Bank',
        ], 'EIDIKOS');

        $this->assertSame('EIDIKOS', $draft['entity']['code']);              // selected entity authoritative
        $this->assertSame('proforma_invoice', $draft['document_type']['value']);
        $this->assertSame(CommercialDraftResolver::STATUS_MATCHED, $draft['client']['status']); // "P.A Knit" -> P.A. knit
        $this->assertStringContainsString('P.A. knit', $draft['client']['name']);

        $codes = collect($draft['standards'])->pluck('code');
        $this->assertTrue($codes->contains('ISO 9001'));
        $this->assertTrue($codes->contains('ISO 14001'));
        $this->assertSame('ISO Management System Certification', $draft['service_category']['name']); // inferred

        $this->assertSame('itemized', $draft['charge_presentation']['value']);
        $this->assertSame('USD', $draft['currency']['value']);
        $this->assertEqualsWithDelta(124, $draft['conversion_rate'], 0.001);

        // Bank resolved WITHIN Eidikos (The City Bank Limited from the entity config).
        $this->assertNotNull($draft['bank']['id']);
        $this->assertStringContainsString('City Bank', $draft['bank']['name']);
        $this->assertSame($this->entityId('EIDIKOS'), BankAccount::query()->withoutGlobalScopes()->find($draft['bank']['id'])->business_entity_id);

        // Laravel calculations — never the model.
        $this->assertEquals(2200, $draft['totals']['subtotal']);
        $this->assertEquals(272800, $draft['totals']['bdt_equivalent']);
        $this->assertEqualsWithDelta(124, $draft['totals']['rate'], 0.001);
    }

    public function test_bank_matching_is_scoped_to_the_selected_entity(): void
    {
        // An SMSEA "City Bank" must NOT be picked when the entity is Eidikos… and
        // vice-versa: under SMSEA, the Eidikos City Bank is invisible.
        BankAccount::query()->forceCreate([
            'business_entity_id' => $this->entityId('SMSEA'), 'beneficiary_name' => 'SMSEA',
            'bank_name' => 'Prime Bank Ltd.', 'account_number' => '2170316017001', 'is_active' => true, 'is_default' => true,
        ]);

        $draft = $this->resolve(['entity_name' => 'SMSEA', 'document_type' => 'invoice', 'bank_name' => 'City Bank'], 'SMSEA');
        // No SMSEA "City Bank" exists → falls back to the SMSEA default (Prime), never Eidikos's.
        $bank = BankAccount::query()->withoutGlobalScopes()->find($draft['bank']['id']);
        $this->assertSame($this->entityId('SMSEA'), $bank->business_entity_id);
        $this->assertStringContainsString('Prime', $bank->bank_name);
    }

    public function test_environmental_parameter_breakdown_and_particular_matching(): void
    {
        $draft = $this->resolve([
            'entity_name' => 'SMSEA', 'document_type' => 'invoice', 'client_name' => 'UNI Garments Limited',
            'selected_packages' => ['Environmental Parameter Testing'],
            'charge_particulars' => ['travel', 'admin'],
            'charge_presentation' => 'breakdown', 'consolidated_amount' => '30000', 'currency' => 'BDT',
        ], 'SMSEA');

        $this->assertSame('component_breakdown', $draft['charge_presentation']['value']);
        $this->assertSame('Environmental Parameter Testing', $draft['standards'][0]['name']);
        $this->assertSame('Environmental and Sustainability Services', $draft['service_category']['name']);
        $names = collect($draft['charge_particulars'])->pluck('name');
        $this->assertTrue($names->contains('Travel & Operational Cost'));  // "travel"
        $this->assertTrue($names->contains('Administration Fee'));         // "admin"
        $this->assertEquals(30000, $draft['totals']['subtotal']);
        $this->assertNull($draft['totals']['bdt_equivalent']);            // BDT — no conversion
        $this->assertFalse($draft['currency']['defaulted']);
    }

    public function test_quotation_consolidated_detection(): void
    {
        $draft = $this->resolve([
            'entity_name' => 'EcoVeritas', 'document_type' => 'quotation', 'client_name' => 'ABC Textiles Ltd.',
            'selected_services' => ['Energy Audit'], 'consolidated_amount' => '45000', 'currency' => 'BDT',
        ], 'ECOVERITAS');

        $this->assertSame('quotation', $draft['document_type']['value']);
        $this->assertSame('consolidated', $draft['charge_presentation']['value']);
        $this->assertSame('Energy Audit', $draft['standards'][0]['name']);
        $this->assertEquals(45000, $draft['totals']['subtotal']);
    }

    public function test_unmatched_client_is_flagged_and_never_created(): void
    {
        $before = Client::query()->count();
        $draft = $this->resolve(['entity_name' => 'SMSEA', 'client_name' => 'Totally New Client Ltd.'], 'SMSEA');

        $this->assertSame(CommercialDraftResolver::STATUS_NOT_MATCHED, $draft['client']['status']);
        $this->assertNull($draft['client']['id']);
        $this->assertSame($before, Client::query()->count()); // resolver never writes
    }

    public function test_defaulted_currency_is_flagged(): void
    {
        $draft = $this->resolve(['entity_name' => 'SMSEA', 'consolidated_amount' => '5000'], 'SMSEA');
        $this->assertTrue($draft['currency']['defaulted']);
        $this->assertSame('BDT', $draft['currency']['value']);
    }

    public function test_extractor_failure_preserves_fallback_without_throwing(): void
    {
        $failing = new class implements CommercialInstructionProvider
        {
            public function name(): string
            {
                return 'boom';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function extract(string $text): array
            {
                throw new \RuntimeException('gemini down');
            }
        };

        $result = (new CommercialInstructionExtractor([$failing]))->extract('SMSEA invoice for X');
        $this->assertFalse($result['ok']);
        $this->assertSame(CommercialInstructionExtractor::blank(), $result['data']);
        $this->assertStringContainsString('Unable to analyze', $result['message']);
    }

    public function test_loose_number_parsing(): void
    {
        $x = new CommercialInstructionExtractor;
        $this->assertEquals(1200, $x->number('USD 1,200'));
        $this->assertEquals(30000, $x->number('30k'));
        $this->assertEquals(139130, $x->number('139130'));
        $this->assertNull($x->number('n/a'));
    }
}
