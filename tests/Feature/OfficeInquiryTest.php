<?php

namespace Tests\Feature;

use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\ServiceInquiry;
use App\Models\User;
use Database\Seeders\StandardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public-inquiry → Office connection: list, review, status, and bridging into
 * the existing client/quotation create flows. All under authenticated /office.
 */
class OfficeInquiryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(StandardSeeder::class);
        $this->user = User::factory()->create();
    }

    private function inquiry(array $overrides = []): ServiceInquiry
    {
        return ServiceInquiry::query()->create(array_merge([
            'business_entity_id' => BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'),
            'name' => 'Test User',
            'company' => 'Test Environmental Industries Ltd.',
            'email' => 'internal-test@smsenvironment.com',
            'phone' => '+8801700000000',
            'service' => 'Environmental Impact Assessment (EIA)',
            'message' => 'Requesting proposal for environmental impact assessment.',
            'source' => 'website',
            'status' => 'new',
        ], $overrides));
    }

    public function test_guest_cannot_access_inquiries(): void
    {
        $this->get(route('inquiries.index'))->assertRedirect(route('login'));
        $this->get(route('inquiries.show', $this->inquiry()))->assertRedirect(route('login'));
    }

    public function test_office_lists_inquiries_newest_first(): void
    {
        $this->inquiry(['name' => 'Older', 'created_at' => now()->subDay()]);
        $this->inquiry(['name' => 'Newer']);

        $this->actingAs($this->user)->get(route('inquiries.index'))->assertOk()
            ->assertSee('Website Inquiries')
            ->assertSee('Test Environmental Industries Ltd.')
            ->assertSee('New');
    }

    public function test_inquiry_detail_shows_data_and_status_new(): void
    {
        $inquiry = $this->inquiry();

        $this->actingAs($this->user)->get(route('inquiries.show', $inquiry))->assertOk()
            ->assertSee('Test User')
            ->assertSee('Environmental Impact Assessment (EIA)')
            ->assertSee('Prepare Quotation')
            ->assertSee('Create Client from Inquiry');
    }

    public function test_status_can_be_updated(): void
    {
        $inquiry = $this->inquiry();

        $this->actingAs($this->user)->patch(route('inquiries.status', $inquiry), ['status' => 'closed'])->assertRedirect();
        $this->assertSame('closed', $inquiry->fresh()->status);
    }

    public function test_create_client_prefills_the_client_form(): void
    {
        $inquiry = $this->inquiry();

        $res = $this->actingAs($this->user)->post(route('inquiries.client', $inquiry))
            ->assertRedirect(route('clients.create'));

        $old = $res->getSession()->get('_old_input');
        $this->assertSame('Test Environmental Industries Ltd.', $old['company_name']);
        $this->assertSame('internal-test@smsenvironment.com', $old['email']);
        // Never auto-creates a client.
        $this->assertSame(0, Client::query()->withoutGlobalScopes()->count());
    }

    public function test_prepare_quotation_maps_service_and_prefills_without_creating(): void
    {
        $client = Client::query()->create(['company_name' => 'Test Environmental Industries Ltd.', 'address' => 'Dhaka']);
        $inquiry = $this->inquiry();

        $quotesBefore = Quotation::query()->withoutGlobalScopes()->count();

        $res = $this->actingAs($this->user)->post(route('inquiries.quotation', $inquiry), ['client_id' => $client->id])
            ->assertRedirect(route('quotations.create'));

        $old = $res->getSession()->get('_old_input');
        $this->assertSame((string) $client->id, (string) $old['client_id']);
        $this->assertNotEmpty($old['standards']); // EIA mapped from catalogue, server-side
        $this->assertStringContainsString('Environmental Impact Assessment', $old['charge_title']);

        // No quotation created and no number consumed.
        $this->assertSame($quotesBefore, Quotation::query()->withoutGlobalScopes()->count());
        // Opening a quotation moves the inquiry to reviewed.
        $this->assertSame('reviewed', $inquiry->fresh()->status);
    }

    public function test_prepare_quotation_requires_a_client(): void
    {
        $this->actingAs($this->user)->post(route('inquiries.quotation', $this->inquiry()), [])
            ->assertSessionHasErrors('client_id');
    }
}
