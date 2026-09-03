<?php

namespace Tests\Feature;

use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\User;
use App\Services\DashboardService;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function useSmsea(): void
    {
        app(CurrentEntity::class)->use(BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));
    }

    private function invoice(array $attrs): ProformaInvoice
    {
        $this->useSmsea();
        $client = Client::query()->firstOrCreate(['company_name' => 'Acme Ltd.'], ['address' => 'Dhaka']);

        return ProformaInvoice::query()->create(array_merge([
            'client_id' => $client->id, 'number' => 'SMSEA/PI/2026/'.rand(1000, 9999),
            'date' => now()->toDateString(), 'charge_presentation' => 'consolidated', 'currency' => 'BDT',
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => 100000, 'adjustment' => 0, 'total' => 100000, 'commercial_status' => 'won',
        ], $attrs));
    }

    public function test_dashboard_loads_with_period_filters(): void
    {
        $user = User::factory()->staff()->create();
        $this->useSmsea();

        foreach (['today', 'month', 'year'] as $p) {
            $this->actingAs($user)->get(route('dashboard', ['period' => $p]))->assertOk();
        }
        $this->actingAs($user)->get(route('dashboard', ['period' => 'custom', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]))->assertOk();
    }

    public function test_currencies_are_grouped_not_summed(): void
    {
        $this->invoice(['currency' => 'BDT', 'total' => 100000, 'subtotal' => 100000]);
        $this->invoice(['currency' => 'USD', 'total' => 5000, 'subtotal' => 5000, 'conversion_rate' => 125]);

        $dash = DashboardService::forPeriod('year', null, null);
        $invoiced = collect($dash->invoiceKpis()['invoiced']);

        $this->assertEqualsCanonicalizing(['BDT', 'USD'], $invoiced->pluck('currency')->all());
        $this->assertSame(100000.0, $invoiced->firstWhere('currency', 'BDT')['value']);
        $this->assertSame(5000.0, $invoiced->firstWhere('currency', 'USD')['value']);
    }

    public function test_won_and_lost_kpis_reflect_status(): void
    {
        $this->invoice(['commercial_status' => 'won', 'total' => 100000, 'subtotal' => 100000]);
        $this->invoice(['commercial_status' => 'lost', 'total' => 50000, 'subtotal' => 50000]);

        $kpis = DashboardService::forPeriod('year', null, null)->invoiceKpis();
        $this->assertSame(100000.0, collect($kpis['won'])->firstWhere('currency', 'BDT')['value']);
        $this->assertSame(50000.0, collect($kpis['lost'])->firstWhere('currency', 'BDT')['value']);
    }
}
