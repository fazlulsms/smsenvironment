<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_primary_screens_render_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $client = Client::query()->create(['company_name' => 'Nav Test Ltd.', 'address' => 'Dhaka']);
        $service = Service::query()->create([
            'name' => 'Environmental Impact Assessment',
            'service_type' => Service::TYPE_BUNDLE,
            'default_unit' => 'Job',
            'is_active' => true,
        ]);
        $bank = BankAccount::query()->create([
            'beneficiary_name' => 'SMS Environmental Alliance',
            'bank_name' => 'Prime Bank Ltd.',
            'account_number' => '2170316017001',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->actingAs($user);

        $routes = [
            route('dashboard'),
            route('clients.index'),
            route('clients.create'),
            route('clients.show', $client),
            route('clients.edit', $client),
            route('services.index'),
            route('services.create'),
            route('services.edit', $service),
            route('quotations.index'),
            route('quotations.create'),
            route('quotations.create', ['client_id' => $client->id]),
            route('proforma-invoices.index'),
            route('proforma-invoices.create'),
            route('bank-accounts.index'),
            route('bank-accounts.create'),
            route('bank-accounts.edit', $bank),
            route('email-deliveries.index'),
            route('settings.edit'),
        ];

        foreach ($routes as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_email_history_filters_apply(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('email-deliveries.index', ['type' => 'quotation', 'status' => 'sent']))
            ->assertOk();
    }
}
