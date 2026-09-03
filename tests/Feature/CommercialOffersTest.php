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

    // A + B + C — each sent document is one offer; independents count separately.
    public function test_quotation_and_invoice_each_count_as_offers(): void
    {
        $this->quotation(['commercial_status' => 'sent']);
        $k = $this->kpis();
        $this->assertSame(1, $this->offerCount($k['sent']));

        $this->invoice(['commercial_status' => 'sent']);
        $k = $this->kpis();
        $this->assertSame(2, $this->offerCount($k['sent']));
        $this->assertSame(180000.0, $this->value($k['sent']));
    }

    // D + E — a linked quotation+PI is ONE offer at the PI value.
    public function test_linked_quotation_and_invoice_count_once_at_invoice_value(): void
    {
        $q = $this->quotation(['commercial_status' => 'sent', 'total' => 100000]);
        $this->invoice(['commercial_status' => 'sent', 'total' => 100000, 'quotation_id' => $q->id]);

        $k = $this->kpis();
        $this->assertSame(1, $this->offerCount($k['sent']));
        $this->assertSame(100000.0, $this->value($k['sent']));
    }

    // F + G — Won from either document type.
    public function test_won_counts_from_quotation_or_invoice(): void
    {
        $this->quotation(['commercial_status' => 'won', 'total' => 150000]);
        $this->invoice(['commercial_status' => 'won', 'total' => 80000]);

        $k = $this->kpis();
        $this->assertSame(2, $this->offerCount($k['won']));
        $this->assertSame(230000.0, $this->value($k['won']));
    }

    // H — linked quotation(Sent) + PI(Won) resolves to ONE Won item.
    public function test_linked_pair_status_uses_invoice(): void
    {
        $q = $this->quotation(['commercial_status' => 'sent', 'total' => 100000]);
        $this->invoice(['commercial_status' => 'won', 'total' => 100000, 'quotation_id' => $q->id]);

        $k = $this->kpis();
        $this->assertSame(1, $this->offerCount($k['won']));
        $this->assertSame(100000.0, $this->value($k['won']));
        $this->assertSame(1, $this->offerCount($k['sent']));  // still one offer overall
    }

    // I — quotation Lost counts as Lost.
    public function test_quotation_lost_counts(): void
    {
        $this->quotation(['commercial_status' => 'lost', 'total' => 50000]);
        $k = $this->kpis();
        $this->assertSame(1, $this->offerCount($k['lost']));
        $this->assertSame(50000.0, $this->value($k['lost']));
    }

    // J — historical draft with a real sent email counts as an offer (root-cause fix).
    public function test_historical_emailed_draft_counts_as_sent(): void
    {
        $inv = $this->invoice(['commercial_status' => 'draft']);
        DocumentEmailDelivery::query()->create([
            'business_entity_id' => $inv->business_entity_id, 'document_type' => 'proforma_invoice',
            'document_id' => $inv->id, 'to_email' => 'c@x.test', 'subject' => 'PI', 'status' => 'sent', 'sent_at' => now(),
        ]);

        $this->assertSame(1, $this->offerCount($this->kpis()['sent']));
    }

    // K — manual Mark Sent works for both.
    public function test_manual_mark_sent(): void
    {
        $user = User::factory()->staff()->create();
        $q = $this->quotation();
        $inv = $this->invoice();

        $this->actingAs($user)->post(route('quotations.mark-sent', $q))->assertRedirect();
        $this->actingAs($user)->post(route('proforma-invoices.mark-sent', $inv))->assertRedirect();

        $this->assertSame('sent', $q->fresh()->commercial_status);
        $this->assertSame('sent', $inv->fresh()->commercial_status);
        $this->assertSame(2, $this->offerCount($this->kpis()['sent']));
    }

    // L — date filtering (a sent offer outside the period is excluded).
    public function test_date_filter_excludes_out_of_period(): void
    {
        $this->quotation(['commercial_status' => 'sent', 'date' => now()->subYears(2)->toDateString(), 'status_updated_at' => now()->subYears(2)]);

        $thisYear = DashboardService::forPeriod('year', null, null)->invoiceKpis();
        $this->assertSame(0, $this->offerCount($thisYear['sent']));
    }

    // M — multi-currency stays grouped, never summed.
    public function test_multi_currency_offers_grouped(): void
    {
        $this->quotation(['commercial_status' => 'sent', 'total' => 100000]);              // BDT
        $this->invoice(['commercial_status' => 'sent', 'currency' => 'USD', 'total' => 2000, 'conversion_rate' => 125]);

        $sent = collect($this->kpis()['sent']);
        $this->assertEqualsCanonicalizing(['BDT', 'USD'], $sent->pluck('currency')->all());
        $this->assertSame(100000.0, $sent->firstWhere('currency', 'BDT')['value']);
        $this->assertSame(2000.0, $sent->firstWhere('currency', 'USD')['value']);
    }

    // N + O — quotations never affect receivables; PI payments do.
    public function test_quotation_value_does_not_affect_receivables(): void
    {
        $this->quotation(['commercial_status' => 'won', 'total' => 999999]);
        $inv = $this->invoice(['commercial_status' => 'won', 'total' => 80000]);
        $inv->payments()->create(['business_entity_id' => $inv->business_entity_id, 'amount' => 30000, 'currency' => 'BDT', 'received_date' => now()->toDateString()]);

        $rk = DashboardService::forPeriod('year', null, null)->receivableKpis();
        $this->assertSame(30000.0, $rk['received']['BDT']);   // only the PI payment
        $this->assertSame(50000.0, $rk['due']['BDT']);         // 80000 - 30000, quotation ignored
    }
}
