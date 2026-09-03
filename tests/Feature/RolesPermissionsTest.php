<?php

namespace Tests\Feature;

use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\DocumentEmailDelivery;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Services\ProformaInvoiceVerificationService;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function useSmsea(): void
    {
        app(CurrentEntity::class)->use(BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));
    }

    private function client(): Client
    {
        return Client::query()->create(['company_name' => 'Test Client Ltd.', 'address' => 'Dhaka']);
    }

    private function quotation(?string $number = null): Quotation
    {
        $this->useSmsea();
        $client = $this->client();

        return Quotation::query()->create([
            'client_id' => $client->id,
            'number' => $number ?: app(DocumentNumberService::class)->quotation(),
            'date' => now()->toDateString(),
            'charge_presentation' => 'consolidated',
            'client_snapshot' => ['company_name' => $client->company_name],
            'settings_snapshot' => ['organization_name' => 'SMS Environmental Alliance'],
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => 1000, 'adjustment' => 0, 'total' => 1000,
        ]);
    }

    private function invoice(?string $number = null): ProformaInvoice
    {
        $this->useSmsea();
        $client = $this->client();

        return ProformaInvoice::query()->create([
            'client_id' => $client->id,
            'number' => $number ?: app(DocumentNumberService::class)->invoice(),
            'date' => now()->toDateString(),
            'charge_presentation' => 'consolidated',
            'client_snapshot' => ['company_name' => $client->company_name],
            'settings_snapshot' => ['organization_name' => 'SMS Environmental Alliance'],
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => 1000, 'adjustment' => 0, 'total' => 1000,
        ]);
    }

    private function markEmailed(Quotation|ProformaInvoice $document): void
    {
        $type = $document instanceof Quotation ? 'quotation' : 'proforma_invoice';
        DocumentEmailDelivery::query()->create([
            'business_entity_id' => $document->business_entity_id,
            'document_type' => $type,
            'document_id' => $document->id,
            'to_email' => 'client@example.com',
            'subject' => 'Your document',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    // ---- SUPER ADMIN -------------------------------------------------------

    public function test_super_admin_can_edit_client(): void
    {
        $this->useSmsea();
        $super = User::factory()->superAdmin()->create();
        $client = $this->client();

        $this->actingAs($super)->put(route('clients.update', $client), [
            'company_name' => 'Renamed Ltd.', 'address' => 'Dhaka',
        ])->assertRedirect();

        $this->assertSame('Renamed Ltd.', $client->fresh()->company_name);
    }

    public function test_super_admin_can_delete_eligible_test_client(): void
    {
        $this->useSmsea();
        $super = User::factory()->superAdmin()->create();
        $client = $this->client();

        $this->actingAs($super)->delete(route('clients.destroy', $client))->assertRedirect();
        $this->assertModelMissing($client);
    }

    public function test_super_admin_can_delete_draft_quotation_and_invoice(): void
    {
        $super = User::factory()->superAdmin()->create();
        $quotation = $this->quotation();
        $invoice = $this->invoice();

        $this->actingAs($super)->delete(route('quotations.destroy', $quotation))->assertRedirect();
        $this->actingAs($super)->delete(route('proforma-invoices.destroy', $invoice))->assertRedirect();

        $this->assertSoftDeleted('quotations', ['id' => $quotation->id]);
        $this->assertSoftDeleted('proforma_invoices', ['id' => $invoice->id]);
    }

    public function test_super_admin_can_delete_issued_document(): void
    {
        $super = User::factory()->superAdmin()->create();
        $quotation = $this->quotation();
        $this->markEmailed($quotation);

        $this->actingAs($super)->delete(route('quotations.destroy', $quotation))->assertRedirect();
        $this->assertSoftDeleted('quotations', ['id' => $quotation->id]);
    }

    public function test_super_admin_can_reach_users_settings_and_entities(): void
    {
        $this->useSmsea();
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->get(route('users.index'))->assertOk();
        $this->actingAs($super)->get(route('settings.edit'))->assertOk();
        $this->actingAs($super)->get(route('entities.index'))->assertOk();
    }

    public function test_super_admin_can_create_a_user(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->post(route('users.store'), [
            'name' => 'New Person', 'email' => 'new@smsea.test', 'role' => User::ROLE_STAFF,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['email' => 'new@smsea.test', 'role' => User::ROLE_STAFF]);
    }

    // ---- ADMIN -------------------------------------------------------------

    public function test_admin_can_do_normal_business_operations(): void
    {
        $this->useSmsea();
        $admin = User::factory()->admin()->create();
        $client = $this->client();

        $this->actingAs($admin)->get(route('clients.index'))->assertOk();
        $this->actingAs($admin)->put(route('clients.update', $client), [
            'company_name' => 'Admin Edited Ltd.', 'address' => 'Dhaka',
        ])->assertRedirect();
        $this->actingAs($admin)->get(route('bank-accounts.index'))->assertOk();
        $this->actingAs($admin)->get(route('email-deliveries.index'))->assertOk();
    }

    public function test_admin_cannot_delete_any_document_draft_or_issued(): void
    {
        // Business rule: only Super Admin may delete. Admin cannot delete even a
        // draft they could otherwise edit — so they can't remove offers from
        // management reporting.
        $admin = User::factory()->admin()->create();

        $draft = $this->quotation();
        $this->actingAs($admin)->delete(route('quotations.destroy', $draft))->assertForbidden();
        $this->assertNotSoftDeleted('quotations', ['id' => $draft->id]);

        $issued = $this->quotation();
        $this->markEmailed($issued);
        $this->actingAs($admin)->delete(route('quotations.destroy', $issued))->assertForbidden();
        $this->assertNotSoftDeleted('quotations', ['id' => $issued->id]);
    }

    public function test_admin_cannot_reach_system_administration(): void
    {
        $this->useSmsea();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('users.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($admin)->get(route('email-accounts.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('entities.index'))->assertForbidden();
    }

    public function test_admin_cannot_delete_client(): void
    {
        $this->useSmsea();
        $admin = User::factory()->admin()->create();
        $client = $this->client();

        $this->actingAs($admin)->delete(route('clients.destroy', $client))->assertForbidden();
        $this->assertModelExists($client);
    }

    // ---- STAFF -------------------------------------------------------------

    public function test_staff_can_create_and_edit_operational_records(): void
    {
        $this->useSmsea();
        $staff = User::factory()->staff()->create();
        $client = $this->client();

        $this->actingAs($staff)->get(route('clients.index'))->assertOk();
        $this->actingAs($staff)->post(route('clients.store'), [
            'company_name' => 'Staff Made Ltd.', 'address' => 'Dhaka',
        ])->assertRedirect();
        $this->actingAs($staff)->put(route('clients.update', $client), [
            'company_name' => 'Staff Edited Ltd.', 'address' => 'Dhaka',
        ])->assertRedirect();
        $this->actingAs($staff)->get(route('quotations.create'))->assertOk();
    }

    public function test_staff_cannot_delete_documents_or_clients(): void
    {
        $staff = User::factory()->staff()->create();
        $quotation = $this->quotation();
        $invoice = $this->invoice();
        $this->useSmsea();
        $client = $this->client();

        $this->actingAs($staff)->delete(route('quotations.destroy', $quotation))->assertForbidden();
        $this->actingAs($staff)->delete(route('proforma-invoices.destroy', $invoice))->assertForbidden();
        $this->actingAs($staff)->delete(route('clients.destroy', $client))->assertForbidden();

        $this->assertNotSoftDeleted('quotations', ['id' => $quotation->id]);
        $this->assertModelExists($client);
    }

    public function test_staff_cannot_reach_admin_or_system_areas(): void
    {
        $this->useSmsea();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get(route('settings.edit'))->assertForbidden();
        $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('bank-accounts.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('email-accounts.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('email-deliveries.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('entities.index'))->assertForbidden();
    }

    // ---- LAST SUPER ADMIN PROTECTION --------------------------------------

    public function test_last_active_super_admin_cannot_be_deactivated(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->patch(route('users.active', $super))->assertRedirect();
        $this->assertTrue($super->fresh()->is_active, 'Last Super Admin must stay active.');
    }

    public function test_last_active_super_admin_cannot_be_downgraded(): void
    {
        $super = User::factory()->superAdmin()->create();

        $this->actingAs($super)->from(route('users.edit', $super))->put(route('users.update', $super), [
            'name' => $super->name, 'email' => $super->email, 'role' => User::ROLE_STAFF,
            'confirm_downgrade' => '1',
        ])->assertSessionHasErrors('role');

        $this->assertTrue($super->fresh()->isSuperAdmin());
    }

    public function test_last_active_super_admin_cannot_be_deleted(): void
    {
        $super = User::factory()->superAdmin()->create();
        $other = User::factory()->staff()->create();

        // Deleting a different user is fine; deleting the last super admin is not.
        $this->actingAs($super)->delete(route('users.destroy', $super))->assertRedirect();
        $this->assertModelExists($super);
    }

    public function test_second_super_admin_can_be_downgraded_with_confirmation(): void
    {
        $super = User::factory()->superAdmin()->create();
        $other = User::factory()->superAdmin()->create();

        $this->actingAs($super)->put(route('users.update', $other), [
            'name' => $other->name, 'email' => $other->email, 'role' => User::ROLE_ADMIN,
            'confirm_downgrade' => '1',
        ])->assertRedirect(route('users.index'));

        $this->assertTrue($other->fresh()->isAdmin());
    }

    // ---- NUMBERING SAFETY --------------------------------------------------

    public function test_deleting_a_draft_does_not_reuse_document_number(): void
    {
        $super = User::factory()->superAdmin()->create();

        $first = $this->quotation();
        $second = $this->quotation();

        $this->actingAs($super)->delete(route('quotations.destroy', $second))->assertRedirect();

        // The next number must skip past the deleted one — never reuse it.
        $next = app(DocumentNumberService::class)->quotation();
        $this->assertNotSame($second->number, $next);
        $this->assertNotSame($first->number, $next);
    }

    // ---- VERIFICATION PRESERVED -------------------------------------------

    public function test_verification_survives_deletion_of_issued_document(): void
    {
        $super = User::factory()->superAdmin()->create();
        $invoice = $this->invoice();
        $invoice->items()->create(['description' => 'Testing', 'amount' => 1000, 'sort_order' => 1]);
        $invoice = app(ProformaInvoiceVerificationService::class)->apply($invoice->load('items'));
        $this->markEmailed($invoice);

        $code = $invoice->verification_id;
        $this->actingAs($super)->delete(route('proforma-invoices.destroy', $invoice))->assertRedirect();

        // Public verification still resolves the (soft-deleted) issued document.
        $this->get(route('verify.show', $code))->assertOk()->assertSee($invoice->number);
    }

    // ---- CASCADE SAFETY ----------------------------------------------------

    public function test_client_with_linked_documents_cannot_be_deleted(): void
    {
        $super = User::factory()->superAdmin()->create();
        $quotation = $this->quotation();
        $client = $quotation->client;

        $this->actingAs($super)->delete(route('clients.destroy', $client))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertModelExists($client);
    }

    // ---- DEACTIVATED LOGIN -------------------------------------------------

    public function test_deactivated_user_cannot_log_in(): void
    {
        $user = User::factory()->staff()->inactive()->create(['email' => 'off@smsea.test']);

        $this->post(route('login.store'), [
            'email' => 'off@smsea.test', 'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
