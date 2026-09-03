<?php

namespace Tests\Feature;

use App\Models\AssessmentSchedule;
use App\Models\Assessor;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\EmailAccount;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\RecordHistory;
use App\Models\User;
use App\Services\ProformaInvoiceVerificationService;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ChangeHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function useSmsea(): void
    {
        app(CurrentEntity::class)->use(BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));
    }

    private function client(): Client
    {
        $this->useSmsea();

        return Client::query()->create(['company_name' => 'History Ltd.', 'address' => 'Dhaka']);
    }

    private function quotation(array $attrs = []): Quotation
    {
        return Quotation::query()->create(array_merge([
            'client_id' => $this->client()->id, 'number' => 'SMSEA/QT/2026/'.rand(1000, 9999),
            'date' => now()->toDateString(), 'charge_presentation' => 'consolidated',
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => 100000, 'adjustment' => 0, 'total' => 100000, 'commercial_status' => 'draft',
        ], $attrs));
    }

    private function invoice(array $attrs = []): ProformaInvoice
    {
        return ProformaInvoice::query()->create(array_merge([
            'client_id' => $this->client()->id, 'number' => 'SMSEA/PI/2026/'.rand(1000, 9999),
            'date' => now()->toDateString(), 'charge_presentation' => 'consolidated', 'currency' => 'BDT',
            'vat_treatment' => 'exclusive', 'vat_rate' => 0, 'vat_amount' => 0,
            'subtotal' => 100000, 'adjustment' => 0, 'total' => 100000, 'commercial_status' => 'draft',
        ], $attrs));
    }

    private function updates(Quotation|ProformaInvoice|Client|AssessmentSchedule $m)
    {
        return $m->histories()->where('action', RecordHistory::ACTION_UPDATED)->get();
    }

    // A + B + C — editing a quotation records an update event with before/after + actor.
    public function test_quotation_edit_records_history_with_before_after_and_actor(): void
    {
        $actor = User::factory()->staff()->create();
        $q = $this->quotation(['subject' => 'Original subject']);
        $this->actingAs($actor);

        $q->update(['subject' => 'Revised subject']);

        $h = $this->updates($q)->first();
        $this->assertNotNull($h, 'An update history event should exist.');
        $this->assertContains('subject', $h->changed_fields_json);
        $this->assertSame('Original subject', $h->before_json['subject']);
        $this->assertSame('Revised subject', $h->after_json['subject']);
        $this->assertSame($actor->id, $h->changed_by);
    }

    // D — an unchanged save creates no history noise.
    public function test_unchanged_save_creates_no_history(): void
    {
        $q = $this->quotation(['subject' => 'Same']);
        $countAfterCreate = $q->histories()->where('action', 'updated')->count();

        $q->update(['subject' => 'Same']);   // no real change
        $q->touch();                          // updated_at only

        $this->assertSame($countAfterCreate, $q->histories()->where('action', 'updated')->count());
    }

    // E + F + G — editing a PI records amount and status changes.
    public function test_invoice_amount_and_status_changes_recorded(): void
    {
        $inv = $this->invoice(['total' => 100000, 'commercial_status' => 'draft']);

        $inv->update(['total' => 120000]);
        $inv->update(['commercial_status' => 'won', 'status_updated_at' => now()]);

        $amount = $this->updates($inv)->firstWhere(fn ($h) => in_array('total', $h->changed_fields_json));
        $this->assertSame('100000.00', (string) $amount->before_json['total']);
        $this->assertSame('120000.00', (string) $amount->after_json['total']);

        $status = $this->updates($inv)->firstWhere(fn ($h) => in_array('commercial_status', $h->changed_fields_json));
        $this->assertSame('draft', $status->before_json['commercial_status']);
        $this->assertSame('won', $status->after_json['commercial_status']);
    }

    // H — editing a client records history.
    public function test_client_edit_recorded(): void
    {
        $c = $this->client();
        $c->update(['company_name' => 'Renamed Client Ltd.']);

        $h = $c->histories()->where('action', 'updated')->first();
        $this->assertSame('History Ltd.', $h->before_json['company_name']);
        $this->assertSame('Renamed Client Ltd.', $h->after_json['company_name']);
    }

    // I + J — schedule date change and assessor-team change via the controller.
    public function test_schedule_date_and_team_changes_recorded(): void
    {
        $this->useSmsea();
        $staff = User::factory()->staff()->create();
        $a1 = Assessor::query()->create(['name' => 'Alice', 'is_active' => true]);
        $a2 = Assessor::query()->create(['name' => 'Bob', 'is_active' => true]);

        $schedule = AssessmentSchedule::query()->create([
            'client_name' => 'Site Co', 'service_name' => 'ISO 14001',
            'scheduled_from' => '2026-10-01', 'scheduled_to' => '2026-10-02',
            'assessment_days' => 2, 'status' => 'planned',
        ]);
        $schedule->assessors()->sync([$a1->id]);

        $this->actingAs($staff)->put(route('schedules.update', $schedule), [
            'service_name' => 'ISO 14001',
            'scheduled_from' => '2026-11-01', 'scheduled_to' => '2026-11-03',
            'assessment_days' => 3, 'status' => 'planned',
            'assessors' => [$a1->id, $a2->id],
        ])->assertRedirect();

        $dateChange = $schedule->histories()->where('action', 'updated')
            ->get()->firstWhere(fn ($h) => in_array('scheduled_from', $h->changed_fields_json ?? []));
        $this->assertNotNull($dateChange, 'Date change should be recorded.');

        $teamChange = $schedule->histories()->where('action', 'updated')
            ->get()->firstWhere(fn ($h) => in_array('assessors', $h->changed_fields_json ?? []));
        $this->assertNotNull($teamChange, 'Assessor-team change should be recorded.');
        $this->assertSame(['Alice'], $teamChange->before_json['assessors']);
        $this->assertSame(['Alice', 'Bob'], $teamChange->after_json['assessors']);
    }

    // K — deleting a payment (Super Admin) preserves the original values in history.
    public function test_payment_deletion_preserves_original_values(): void
    {
        $inv = $this->invoice();
        $payment = $inv->payments()->create([
            'business_entity_id' => $inv->business_entity_id, 'amount' => 500,
            'currency' => 'BDT', 'received_date' => now()->toDateString(),
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('proforma-invoices.payments.destroy', [$inv, $payment]))->assertRedirect();

        $h = RecordHistory::query()
            ->where('auditable_type', $payment->getMorphClass())
            ->where('auditable_id', $payment->id)
            ->where('action', 'deleted')->first();
        $this->assertNotNull($h, 'Payment deletion should be recorded.');
        $this->assertSame('500.00', (string) $h->before_json['amount']);
    }

    // L — Super Admin deleting a document records a delete-history event.
    public function test_super_admin_delete_records_delete_event(): void
    {
        $q = $this->quotation();
        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('quotations.destroy', $q))->assertRedirect();

        $this->assertDatabaseHas('record_histories', [
            'auditable_type' => $q->getMorphClass(),
            'auditable_id' => $q->id,
            'action' => 'deleted',
        ]);
    }

    // O — secrets are never written into history; only a safe note is stored.
    public function test_email_account_secret_never_written_to_history(): void
    {
        $this->useSmsea();
        $account = EmailAccount::query()->create([
            'business_entity_id' => BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'),
            'label' => 'Primary', 'host' => 'smtp.test', 'from_address' => 'a@test',
            'password' => 'initial-secret',
        ]);

        $account->update(['password' => 'new-super-secret', 'host' => 'smtp2.test']);

        $secretChange = $account->histories()->where('action', 'updated')->get()
            ->firstWhere(fn ($h) => $h->note !== null);
        $this->assertNotNull($secretChange, 'A credential-change note should exist.');
        $this->assertStringContainsString('Credential', $secretChange->note);

        // The password VALUE (plaintext or encrypted) must never appear in history.
        // The field name may appear in a safe note; the secret value must not.
        $allJson = RecordHistory::query()->get()
            ->map(fn ($h) => json_encode([$h->before_json, $h->after_json]))
            ->implode(' ');
        $this->assertStringNotContainsString('new-super-secret', $allJson);
        $this->assertStringNotContainsString('initial-secret', $allJson);
    }

    // P — history is immutable through the app: no update/delete route exists for it.
    public function test_history_has_no_mutation_routes(): void
    {
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $this->assertStringNotContainsString('record-histories', $uri, 'History must not be mutable via routes.');
            $this->assertStringNotContainsString('record_histories', $uri);
        }
        // And the model exposes no destroy/update controller anywhere.
        $this->assertFalse(Route::has('record-histories.destroy'));
        $this->assertFalse(Route::has('histories.destroy'));
    }

    // Q — editing an issued document keeps its verification snapshot intact.
    public function test_editing_does_not_break_document_verification(): void
    {
        $inv = $this->invoice();
        $inv->items()->create(['description' => 'Testing', 'amount' => 100000, 'sort_order' => 1]);
        $inv = app(ProformaInvoiceVerificationService::class)->apply($inv->load('items'));
        $code = $inv->verification_id;

        // A later edit of a non-verification field must not change the code.
        $inv->update(['notes' => 'Added a note after issue.']);

        $this->assertSame($code, $inv->fresh()->verification_id);
        $this->get(route('verify.show', $code))->assertOk()->assertSee($inv->number);
    }
}
