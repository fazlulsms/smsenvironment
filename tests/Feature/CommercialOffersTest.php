<?php

namespace Tests\Feature;

use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\DocumentEmailDelivery;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialOffersTest extends TestCase
{
    use RefreshDatabase;

    private function useSmsea(): void
    {
        app(CurrentEntity::class)->use(BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));
    }

    private function client(): Client
    {
        return Client::query()->firstOrCreate(['company_name' => 'Acme Ltd.'], ['address' => 'Dhaka']);
    }

    private function quotation(array $attrs = []): Quotation
    {
        $this->useSmsea();

        return Quotation::query()->create(array_merge([
            'client_id' => $this->client()->id, 'number' => 'SMSEA/QT/2026/'.rand(1000, 9999),
            'date' => now()->toDateString(), 'charge_presentation' => 'consolidated',
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => 100000, 'adjustment' => 0, 'total' => 100000, 'commercial_status' => 'draft',
        ], $attrs));
    }

    private function invoice(array $attrs = []): ProformaInvoice
    {
        $this->useSmsea();

        return ProformaInvoice::query()->create(array_merge([
            'client_id' => $this->client()->id, 'number' => 'SMSEA/PI/2026/'.rand(1000, 9999),
            'date' => now()->toDateString(), 'charge_presentation' => 'consolidated', 'currency' => 'BDT',
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => 80000, 'adjustment' => 0, 'total' => 80000, 'commercial_status' => 'draft',
        ], $attrs));
    }

    private function kpis(): array
    {
        $this->useSmsea();

        return DashboardService::forPeriod('year', null, null)->invoiceKpis();
    }

    private function offerCount(array $rows): int
    {
        return (int) collect($rows)->sum('count');
    }

    private function value(array $rows): float
    {
        return (float) collect($rows)->sum('value');
    }

    // A — a retained DRAFT quotation counts as Created (no send needed).
    public function test_draft_quotation_counts_as_created(): void
    {
        $this->quotation(['commercial_status' => 'draft', 'total' => 100000]);

        $k = $this->kpis();
        $this->assertSame(1, $this->offerCount($k['created']));
        $this->assertSame(100000.0, $this->value($k['created']));
    }

    // B — a retained DRAFT proforma invoice counts as Created.
    public function test_draft_invoice_counts_as_created(): void
    {
        $this->invoice(['commercial_status' => 'draft', 'total' => 80000]);

        $k = $this->kpis();
        $this->assertSame(1, $this->offerCount($k['created']));
        $this->assertSame(80000.0, $this->value($k['created']));
    }

    // C — NO email history is required for a document to be counted as Created.
    public function test_created_requires_no_email_history(): void
    {
        $this->quotation(['commercial_status' => 'draft']);

        $k = $this->kpis();
        $this->assertSame(1, $this->offerCount($k['created']));
        $this->assertSame(0, $k['sent_count']);   // nothing was sent through the app
    }

    // D — Mark as Sent does NOT change the Created count (only the secondary figure).
    public function test_mark_sent_does_not_change_created(): void
    {
        $user = User::factory()->staff()->create();
        $q = $this->quotation();

        $before = $this->kpis();
        $this->assertSame(1, $this->offerCount($before['created']));
        $this->assertSame(0, $before['sent_count']);

        $this->actingAs($user)->post(route('quotations.mark-sent', $q))->assertRedirect();

        $after = $this->kpis();
        $this->assertSame(1, $this->offerCount($after['created']));   // unchanged
        $this->assertSame(1, $after['sent_count']);                   // secondary indicator moves
    }

    // E — Won changes Won but not Created.
    public function test_won_does_not_change_created(): void
    {
        $this->quotation(['commercial_status' => 'won', 'total' => 150000]);

        $k = $this->kpis();
        $this->assertSame(1, $this->offerCount($k['created']));
        $this->assertSame(1, $this->offerCount($k['won']));
        $this->assertSame(150000.0, $this->value($k['won']));
    }

    // F — Lost changes Lost but not Created.
    public function test_lost_does_not_change_created(): void
    {
        $this->quotation(['commercial_status' => 'lost', 'total' => 50000]);

        $k = $this->kpis();
        $this->assertSame(1, $this->offerCount($k['created']));
        $this->assertSame(1, $this->offerCount($k['lost']));
        $this->assertSame(50000.0, $this->value($k['lost']));
    }

    // G — a soft-deleted quotation is excluded from Created.
    public function test_soft_deleted_quotation_excluded(): void
    {
        $q = $this->quotation(['commercial_status' => 'draft']);
        $this->assertSame(1, $this->offerCount($this->kpis()['created']));

        $q->delete();   // soft delete
        $this->assertSame(0, $this->offerCount($this->kpis()['created']));
    }

    // H — a soft-deleted proforma invoice is excluded from Created.
    public function test_soft_deleted_invoice_excluded(): void
    {
        $inv = $this->invoice(['commercial_status' => 'draft']);
        $this->assertSame(1, $this->offerCount($this->kpis()['created']));

        $inv->delete();   // soft delete
        $this->assertSame(0, $this->offerCount($this->kpis()['created']));
    }

    // I — a linked quotation+PI is ONE created offer at the PI value.
    public function test_linked_quotation_and_invoice_count_once(): void
    {
        $q = $this->quotation(['total' => 100000]);
        $this->invoice(['total' => 90000, 'quotation_id' => $q->id]);

        $k = $this->kpis();
        $this->assertSame(1, $this->offerCount($k['created']));
        $this->assertSame(90000.0, $this->value($k['created']));   // PI value wins
    }

    // J — unlinked quotation + PI count twice.
    public function test_unlinked_quotation_and_invoice_count_twice(): void
    {
        $this->quotation(['total' => 100000]);
        $this->invoice(['total' => 80000]);

        $k = $this->kpis();
        $this->assertSame(2, $this->offerCount($k['created']));
        $this->assertSame(180000.0, $this->value($k['created']));
    }

    // K — date filtering uses the created/document date.
    public function test_date_filter_uses_created_date(): void
    {
        // A quotation dated two years ago (regardless of any status timestamp).
        $this->quotation(['commercial_status' => 'draft', 'date' => now()->subYears(2)->toDateString()]);

        $this->useSmsea();
        $thisYear = DashboardService::forPeriod('year', null, null)->invoiceKpis();
        $this->assertSame(0, $this->offerCount($thisYear['created']));

        $wide = DashboardService::forPeriod('custom', now()->subYears(3)->toDateString(), now()->toDateString())->invoiceKpis();
        $this->assertSame(1, $this->offerCount($wide['created']));
    }

    // L — multi-currency stays grouped, never summed.
    public function test_multi_currency_created_grouped(): void
    {
        $this->quotation(['total' => 100000]);                                                       // BDT
        $this->invoice(['currency' => 'USD', 'total' => 2000, 'conversion_rate' => 125]);            // USD

        $created = collect($this->kpis()['created']);
        $this->assertEqualsCanonicalizing(['BDT', 'USD'], $created->pluck('currency')->all());
        $this->assertSame(100000.0, $created->firstWhere('currency', 'BDT')['value']);
        $this->assertSame(2000.0, $created->firstWhere('currency', 'USD')['value']);
    }

    // M — quotations never affect receivables; PI payments do.
    public function test_receivables_remain_pi_only(): void
    {
        $this->quotation(['commercial_status' => 'won', 'total' => 999999]);
        $inv = $this->invoice(['commercial_status' => 'won', 'total' => 80000]);
        $inv->payments()->create(['business_entity_id' => $inv->business_entity_id, 'amount' => 30000, 'currency' => 'BDT', 'received_date' => now()->toDateString()]);

        $rk = DashboardService::forPeriod('year', null, null)->receivableKpis();
        $this->assertSame(30000.0, $rk['received']['BDT']);   // only the PI payment
        $this->assertSame(50000.0, $rk['due']['BDT']);         // 80000 - 30000, quotation ignored
    }

    // N — historical retained documents count with no send/status backfill.
    public function test_historical_documents_included_without_backfill(): void
    {
        // Pure legacy records: draft, no email history, dated last year.
        $this->quotation(['commercial_status' => 'draft', 'date' => now()->subMonths(6)->toDateString()]);
        $this->invoice(['commercial_status' => 'draft', 'date' => now()->subMonths(6)->toDateString()]);

        $this->useSmsea();
        $k = DashboardService::forPeriod('custom', now()->subYear()->toDateString(), now()->toDateString())->invoiceKpis();
        $this->assertSame(2, $this->offerCount($k['created']));
        $this->assertSame(180000.0, $this->value($k['created']));
        $this->assertSame(0, $k['sent_count']);   // nothing fabricated
    }

    // Guard — an emailed draft still counts once as Created and shows in the secondary sent figure.
    public function test_emailed_draft_counts_created_and_sent_secondary(): void
    {
        $inv = $this->invoice(['commercial_status' => 'draft']);
        DocumentEmailDelivery::query()->create([
            'business_entity_id' => $inv->business_entity_id, 'document_type' => 'proforma_invoice',
            'document_id' => $inv->id, 'to_email' => 'c@x.test', 'subject' => 'PI', 'status' => 'sent', 'sent_at' => now(),
        ]);

        $k = $this->kpis();
        $this->assertSame(1, $this->offerCount($k['created']));
        $this->assertSame(1, $k['sent_count']);
    }
}
