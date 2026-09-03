<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\InvoicePayment;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\User;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Enforces the single governance rule: ONLY Super Admin may delete records.
 * Admin and Staff must be blocked server-side (403) AND must not see the UI
 * controls, for every destroy endpoint in /office.
 */
class DeletePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function useSmsea(): void
    {
        app(CurrentEntity::class)->use(BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));
    }

    private function client(): Client
    {
        $this->useSmsea();

        return Client::query()->create(['company_name' => 'Del Test Ltd.', 'address' => 'Dhaka']);
    }

    private function quotation(): Quotation
    {
        return Quotation::query()->create([
            'client_id' => $this->client()->id, 'number' => 'SMSEA/QT/2026/'.rand(1000, 9999),
            'date' => now()->toDateString(), 'charge_presentation' => 'consolidated',
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => 1000, 'adjustment' => 0, 'total' => 1000,
        ]);
    }

    private function invoice(): ProformaInvoice
    {
        return ProformaInvoice::query()->create([
            'client_id' => $this->client()->id, 'number' => 'SMSEA/PI/2026/'.rand(1000, 9999),
            'date' => now()->toDateString(), 'charge_presentation' => 'consolidated', 'currency' => 'BDT',
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => 1000, 'adjustment' => 0, 'total' => 1000,
        ]);
    }

    private function payment(ProformaInvoice $inv): InvoicePayment
    {
        return $inv->payments()->create([
            'business_entity_id' => $inv->business_entity_id, 'amount' => 500,
            'currency' => 'BDT', 'received_date' => now()->toDateString(),
        ]);
    }

    private function service(): Service
    {
        $this->useSmsea();

        return Service::query()->create(['name' => 'Del Service', 'category' => 'Testing']);
    }

    private function bank(): BankAccount
    {
        $this->useSmsea();

        return BankAccount::query()->create([
            'beneficiary_name' => 'SMSEA', 'bank_name' => 'Test Bank',
            'account_number' => '123456', 'is_active' => true,
        ]);
    }

    // ---- QUOTATION ---------------------------------------------------------

    public function test_super_admin_can_delete_quotation(): void
    {
        $q = $this->quotation();
        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('quotations.destroy', $q))->assertRedirect();
        $this->assertSoftDeleted('quotations', ['id' => $q->id]);
    }

    public function test_admin_and_staff_cannot_delete_quotation(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $q = $this->quotation();
            $this->actingAs(User::factory()->{$role}()->create())
                ->delete(route('quotations.destroy', $q))->assertForbidden();
            $this->assertNotSoftDeleted('quotations', ['id' => $q->id]);
        }
    }

    // ---- PROFORMA INVOICE --------------------------------------------------

    public function test_super_admin_can_delete_invoice(): void
    {
        $inv = $this->invoice();
        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('proforma-invoices.destroy', $inv))->assertRedirect();
        $this->assertSoftDeleted('proforma_invoices', ['id' => $inv->id]);
    }

    public function test_admin_and_staff_cannot_delete_invoice(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $inv = $this->invoice();
            $this->actingAs(User::factory()->{$role}()->create())
                ->delete(route('proforma-invoices.destroy', $inv))->assertForbidden();
            $this->assertNotSoftDeleted('proforma_invoices', ['id' => $inv->id]);
        }
    }

    // ---- PAYMENT -----------------------------------------------------------

    public function test_super_admin_can_delete_payment(): void
    {
        $inv = $this->invoice();
        $p = $this->payment($inv);
        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('proforma-invoices.payments.destroy', [$inv, $p]))->assertRedirect();
        $this->assertModelMissing($p);
    }

    public function test_admin_and_staff_cannot_delete_payment(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $inv = $this->invoice();
            $p = $this->payment($inv);
            $this->actingAs(User::factory()->{$role}()->create())
                ->delete(route('proforma-invoices.payments.destroy', [$inv, $p]))->assertForbidden();
            $this->assertModelExists($p);
        }
    }

    // ---- CLIENT ------------------------------------------------------------

    public function test_admin_and_staff_cannot_delete_client(): void
    {
        foreach (['admin', 'staff'] as $role) {
            $c = $this->client();
            $this->actingAs(User::factory()->{$role}()->create())
                ->delete(route('clients.destroy', $c))->assertForbidden();
            $this->assertModelExists($c);
        }
    }

    // ---- SERVICE (master data) --------------------------------------------

    public function test_super_admin_can_delete_service_but_admin_cannot(): void
    {
        $s = $this->service();
        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('services.destroy', $s))->assertForbidden();
        $this->assertModelExists($s);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('services.destroy', $s))->assertRedirect();
        $this->assertModelMissing($s);
    }

    // ---- BANK (master data) ------------------------------------------------

    public function test_super_admin_can_delete_bank_but_admin_cannot(): void
    {
        $b = $this->bank();
        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('bank-accounts.destroy', $b))->assertForbidden();
        $this->assertModelExists($b);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('bank-accounts.destroy', $b))->assertRedirect();
        $this->assertModelMissing($b);
    }

    // ---- ASSESSOR (no delete route at all — deactivation only) -------------

    public function test_assessor_has_no_destroy_route(): void
    {
        $this->assertFalse(
            Route::has('assessors.destroy'),
            'Assessors are retired via deactivation; there must be no delete route.'
        );
    }

    // ---- UI: Delete controls hidden for Admin & Staff ----------------------

    // The destroy route shares its URL with the show route (same path, different
    // verb) and the layout carries a shared strong-delete modal template, so we
    // assert on the unique confirm text that only renders on an actual delete
    // TRIGGER: "Delete draft …" for documents, "Remove this payment entry" for
    // a payment. These appear only inside a passing delete authorization check.
    public function test_delete_controls_hidden_from_admin_and_staff_on_documents(): void
    {
        $q = $this->quotation();
        $inv = $this->invoice();
        $this->payment($inv);

        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->{$role}()->create();

            $qHtml = $this->actingAs($user)->get(route('quotations.show', $q))->assertOk()->getContent();
            $this->assertStringNotContainsString('Delete draft', $qHtml, "$role must not see a quotation delete trigger");

            $iHtml = $this->actingAs($user)->get(route('proforma-invoices.show', $inv))->assertOk()->getContent();
            $this->assertStringNotContainsString('Delete draft', $iHtml, "$role must not see an invoice delete trigger");
            $this->assertStringNotContainsString('Remove this payment entry', $iHtml, "$role must not see a payment delete trigger");
        }
    }

    public function test_delete_controls_visible_to_super_admin_on_documents(): void
    {
        $q = $this->quotation();
        $inv = $this->invoice();
        $this->payment($inv);
        $super = User::factory()->superAdmin()->create();

        $qHtml = $this->actingAs($super)->get(route('quotations.show', $q))->assertOk()->getContent();
        $this->assertStringContainsString('Delete draft', $qHtml, 'Super Admin should see the quotation delete trigger');

        $iHtml = $this->actingAs($super)->get(route('proforma-invoices.show', $inv))->assertOk()->getContent();
        $this->assertStringContainsString('Delete draft', $iHtml, 'Super Admin should see the invoice delete trigger');
        $this->assertStringContainsString('Remove this payment entry', $iHtml, 'Super Admin should see the payment delete trigger');
    }
}
