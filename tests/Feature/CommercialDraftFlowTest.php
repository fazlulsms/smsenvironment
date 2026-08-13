<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Standard;
use App\Models\User;
use App\Services\Commercial\CommercialInstructionExtractor;
use App\Services\Commercial\CommercialInstructionProvider;
use Database\Seeders\ChargeParticularSeeder;
use Database\Seeders\StandardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialDraftFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->seed(StandardSeeder::class);
        $this->seed(ChargeParticularSeeder::class);
    }

    private function bindExtractor(array $payload, bool $fail = false): void
    {
        $provider = new class($payload, $fail) implements CommercialInstructionProvider
        {
            public function __construct(private array $payload, private bool $fail) {}

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
                if ($this->fail) {
                    throw new \RuntimeException('down');
                }

                return $this->payload;
            }
        };

        $this->app->instance(CommercialInstructionExtractor::class, new CommercialInstructionExtractor([$provider]));
    }

    private function eidikosId(): int
    {
        return BusinessEntity::query()->where('entity_code', 'EIDIKOS')->value('id');
    }

    public function test_page_renders_with_copy_format(): void
    {
        $this->actingAs($this->user)->get(route('ai-draft.index'))->assertOk()
            ->assertSee('Analyze with Gemini')->assertSee('Copy Request Format');
    }

    public function test_analyze_renders_review_panel(): void
    {
        Client::query()->create(['company_name' => 'P.A. knit Composite Ltd.', 'address' => 'Bhaluka']);
        $this->bindExtractor([
            'entity_name' => 'Eidikos', 'document_type' => 'PI', 'client_name' => 'P.A Knit',
            'selected_standards' => ['ISO 9001', 'ISO 14001'], 'charge_presentation' => 'itemized',
            'itemized_rows' => [['description' => 'ISO 9001 Certification Fee', 'amount' => '1200'], ['description' => 'ISO 14001 Certification Fee', 'amount' => '1000']],
            'currency' => 'USD', 'conversion_rate' => '124', 'bank_name' => 'City Bank',
        ]);

        $this->actingAs($this->user)->post(route('ai-draft.analyze'), [
            'entity_id' => $this->eidikosId(), 'instruction' => 'Eidikos P.A Knit ISO 9001 14001 1200 1000 USD 124 City Bank PI',
        ])->assertOk()
            ->assertSee('Detected Draft')
            ->assertSee('P.A. knit Composite Ltd.')
            ->assertSee('ISO 9001')
            ->assertSee('272,800.00')      // Laravel-calculated BDT equivalent
            ->assertSee('Apply to Proforma Invoice');
    }

    public function test_apply_prefills_invoice_form_without_creating_anything(): void
    {
        $client = Client::query()->create(['company_name' => 'P.A. knit Composite Ltd.', 'address' => 'Bhaluka']);
        $eidikos = BusinessEntity::query()->where('entity_code', 'EIDIKOS')->firstOrFail();
        $bank = BankAccount::query()->forEntity($eidikos->id)->first();
        $std = Standard::query()->where('code', 'ISO 9001')->firstOrFail();

        $invoicesBefore = ProformaInvoice::query()->withoutGlobalScopes()->count();
        $clientsBefore = Client::query()->count();

        $response = $this->actingAs($this->user)->post(route('ai-draft.apply'), [
            'entity_id' => $eidikos->id, 'target' => 'proforma_invoice',
            'client_id' => $client->id, 'service_category_id' => null,
            'standards' => [$std->id], 'charge_presentation' => 'itemized',
            'currency' => 'USD', 'conversion_rate' => 124, 'bank_account_id' => $bank->id,
            'item_description' => ['ISO 9001 Certification Fee'], 'item_amount' => ['1200'],
        ]);

        $response->assertRedirect(route('proforma-invoices.create'));

        // Nothing issued: no document, no client, no number consumed.
        $this->assertSame($invoicesBefore, ProformaInvoice::query()->withoutGlobalScopes()->count());
        $this->assertSame($clientsBefore, Client::query()->count());

        // The values are flashed as old input for the create form to pick up.
        $old = $response->getSession()->get('_old_input');
        $this->assertSame((string) $client->id, (string) $old['client_id']);
        $this->assertSame('USD', $old['currency']);
        $this->assertSame([$std->id], $old['standards']);
        $this->assertSame('itemized', $old['charge_presentation']);
    }

    public function test_apply_drops_a_bank_from_another_entity(): void
    {
        $eidikos = BusinessEntity::query()->where('entity_code', 'EIDIKOS')->firstOrFail();
        // A bank owned by SMSEA must never be applied under Eidikos.
        $smseaBank = BankAccount::query()->forceCreate([
            'business_entity_id' => BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'),
            'beneficiary_name' => 'S', 'bank_name' => 'Prime Bank Ltd.', 'account_number' => '9', 'is_active' => true, 'is_default' => true,
        ]);

        $response = $this->actingAs($this->user)->post(route('ai-draft.apply'), [
            'entity_id' => $eidikos->id, 'target' => 'proforma_invoice', 'charge_presentation' => 'consolidated',
            'bank_account_id' => $smseaBank->id, 'amount' => 1000,
        ])->assertRedirect(route('proforma-invoices.create'));

        $old = $response->getSession()->get('_old_input');
        $this->assertArrayNotHasKey('bank_account_id', $old); // cross-entity bank dropped
    }

    public function test_analyze_failure_preserves_instruction(): void
    {
        $this->bindExtractor([], fail: true);

        $this->actingAs($this->user)->post(route('ai-draft.analyze'), [
            'entity_id' => $this->eidikosId(), 'instruction' => 'MY IMPORTANT REQUEST TEXT',
        ])->assertOk()
            ->assertSee('Unable to analyze automatically')
            ->assertSee('MY IMPORTANT REQUEST TEXT'); // text not lost
    }
}
