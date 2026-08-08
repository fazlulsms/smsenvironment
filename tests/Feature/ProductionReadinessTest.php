<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_routes_are_protected(): void
    {
        $this->get(route('quotations.index'))->assertRedirect(route('login'));
        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_default_bank_is_used_for_new_quotation_when_none_selected(): void
    {
        $user = User::factory()->create();
        [$client, $service, $defaultBank] = $this->setupData();
        BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Other Bank',
            'account_number' => '987654',
            'is_active' => true,
            'is_default' => false,
        ]);

        $this->actingAs($user)->post(route('quotations.store'), [
            'client_id' => $client->id,
            'date' => '2026-08-08',
            'items' => [[
                'service_id' => $service->id,
                'description' => '',
                'unit' => '',
                'quantity' => 1,
                'unit_rate' => 1000,
            ]],
        ])->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $this->assertSame($defaultBank->id, $quotation->bank_account_id);
        $this->assertSame('Default Bank', $quotation->bank_snapshot['bank_name']);
    }

    public function test_document_destroy_soft_deletes_business_record(): void
    {
        $user = User::factory()->create();
        [$client, $service, $bank] = $this->setupData();

        $this->actingAs($user)->post(route('quotations.store'), [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'items' => [[
                'service_id' => $service->id,
                'description' => '',
                'unit' => '',
                'quantity' => 1,
                'unit_rate' => 1000,
            ]],
        ])->assertRedirect();

        $quotation = Quotation::query()->firstOrFail();
        $this->actingAs($user)->delete(route('quotations.destroy', $quotation))->assertRedirect();

        $this->assertSoftDeleted('quotations', ['id' => $quotation->id]);
    }

    public function test_document_list_searches_by_number_and_client(): void
    {
        $user = User::factory()->create();
        [$client, $service, $bank] = $this->setupData();

        $this->actingAs($user)->post(route('quotations.store'), [
            'client_id' => $client->id,
            'bank_account_id' => $bank->id,
            'date' => '2026-08-08',
            'items' => [[
                'service_id' => $service->id,
                'description' => '',
                'unit' => '',
                'quantity' => 1,
                'unit_rate' => 1000,
            ]],
        ])->assertRedirect();

        $this->actingAs($user)->get(route('quotations.index', ['search' => 'Reliable Client']))
            ->assertOk()
            ->assertSee('Reliable Client Ltd.');

        $this->actingAs($user)->get(route('quotations.index', ['search' => 'SMSEA/QT']))
            ->assertOk()
            ->assertSee('SMSEA/QT/2026/0001');
    }

    private function setupData(): array
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

        $client = Client::query()->create([
            'company_name' => 'Reliable Client Ltd.',
            'address' => 'Dhaka, Bangladesh',
        ]);

        $service = Service::query()->create([
            'name' => 'Ambient Air Quality Test',
            'default_description' => 'Ambient Air Quality Test',
            'default_unit' => 'Job',
            'default_rate' => 1000,
            'is_active' => true,
        ]);

        $bank = BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Default Bank',
            'account_number' => '123456',
            'is_active' => true,
            'is_default' => true,
        ]);

        return [$client, $service, $bank];
    }
}
