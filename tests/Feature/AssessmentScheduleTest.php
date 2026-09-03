<?php

namespace Tests\Feature;

use App\Mail\OperationalMail;
use App\Models\AssessmentSchedule;
use App\Models\Assessor;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\Setting;
use App\Models\User;
use App\Support\CurrentEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AssessmentScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function useSmsea(): void
    {
        app(CurrentEntity::class)->use(BusinessEntity::query()->where('entity_code', 'SMSEA')->value('id'));
    }

    private function client(string $email = 'client@acme.test'): Client
    {
        return Client::query()->create(['company_name' => 'Acme Ltd.', 'address' => 'Dhaka', 'email' => $email]);
    }

    private function assessor(string $name, ?string $email = null): Assessor
    {
        return Assessor::query()->create(['name' => $name, 'email' => $email, 'is_active' => true]);
    }

    // ---- ASSESSORS ---------------------------------------------------------

    public function test_admin_can_create_assessor_but_staff_cannot(): void
    {
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($staff)->get(route('assessors.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('assessors.store'), ['name' => 'Mr A', 'email' => 'a@x.test', 'is_active' => '1'])->assertRedirect();
        $this->assertDatabaseHas('assessors', ['name' => 'Mr A']);
    }

    // ---- SCHEDULES ---------------------------------------------------------

    public function test_create_schedule_with_multiple_assessors_and_assessor_days(): void
    {
        $this->useSmsea();
        $user = User::factory()->staff()->create();
        $client = $this->client();
        $a1 = $this->assessor('A1', 'a1@x.test');
        $a2 = $this->assessor('A2', 'a2@x.test');
        $a3 = $this->assessor('A3');

        $this->actingAs($user)->post(route('schedules.store'), [
            'client_id' => $client->id, 'service_name' => 'EIA',
            'scheduled_from' => '2026-09-10', 'scheduled_to' => '2026-09-11',
            'status' => 'planned', 'assessors' => [$a1->id, $a2->id, $a3->id],
        ])->assertRedirect();

        $schedule = AssessmentSchedule::query()->latest('id')->first();
        $this->assertSame(2, $schedule->assessment_days);            // 10th + 11th
        $this->assertSame(3, $schedule->assessors()->count());
        $this->assertSame(6, $schedule->assessorDays());             // 2 days × 3 assessors
    }

    public function test_schedule_can_be_created_without_invoice(): void
    {
        $this->useSmsea();
        $user = User::factory()->staff()->create();

        $this->actingAs($user)->post(route('schedules.store'), [
            'client_name' => 'Ad-hoc Co', 'service_name' => 'Noise Test',
            'scheduled_from' => '2026-09-10', 'scheduled_to' => '2026-09-10', 'status' => 'planned',
        ])->assertRedirect();

        $this->assertDatabaseHas('assessment_schedules', ['client_name' => 'Ad-hoc Co', 'proforma_invoice_id' => null]);
    }

    public function test_complete_sets_reassessment_and_starts_new_cycle(): void
    {
        $this->useSmsea();
        $user = User::factory()->staff()->create();
        $schedule = AssessmentSchedule::query()->create([
            'client_name' => 'X', 'service_name' => 'EIA', 'scheduled_from' => '2026-09-10',
            'scheduled_to' => '2026-09-10', 'assessment_days' => 1, 'status' => 'planned',
            'reminder_sent_at' => now(), // pretend an old cycle marker
        ]);

        $this->actingAs($user)->post(route('schedules.complete', $schedule), [
            'completed_date' => '2026-09-11', 'next_reassessment_date' => '2027-09-11', 'reminder_enabled' => '1',
        ])->assertRedirect();

        $schedule->refresh();
        $this->assertSame('completed', $schedule->status);
        $this->assertSame('2027-09-11', $schedule->next_reassessment_date->toDateString());
        $this->assertNull($schedule->reminder_sent_at); // reset for the new cycle
    }

    public function test_cancel_requires_admin(): void
    {
        $this->useSmsea();
        $staff = User::factory()->staff()->create();
        $admin = User::factory()->admin()->create();
        $schedule = AssessmentSchedule::query()->create(['client_name' => 'X', 'service_name' => 'EIA', 'scheduled_from' => '2026-09-10', 'scheduled_to' => '2026-09-10', 'assessment_days' => 1, 'status' => 'planned']);

        $this->actingAs($staff)->post(route('schedules.cancel', $schedule))->assertForbidden();
        $this->actingAs($admin)->post(route('schedules.cancel', $schedule))->assertRedirect();
        $this->assertSame('cancelled', $schedule->fresh()->status);
    }

    // ---- SCHEDULE EMAIL ----------------------------------------------------

    public function test_schedule_email_sends_to_assessors_with_email(): void
    {
        Mail::fake();
        $this->useSmsea();
        $user = User::factory()->staff()->create();
        $schedule = AssessmentSchedule::query()->create(['client_name' => 'Acme', 'service_name' => 'EIA', 'scheduled_from' => '2026-09-10', 'scheduled_to' => '2026-09-11', 'assessment_days' => 2, 'status' => 'planned']);
        $schedule->assessors()->sync([$this->assessor('A1', 'a1@x.test')->id, $this->assessor('A2', 'a2@x.test')->id, $this->assessor('NoEmail')->id]);

        $this->actingAs($user)->post(route('schedules.email', $schedule))->assertRedirect();

        Mail::assertSent(OperationalMail::class, fn ($m) => $m->hasTo('a1@x.test') && $m->hasCc('a2@x.test'));
        $this->assertDatabaseHas('document_email_deliveries', ['document_type' => 'assessment_schedule', 'document_id' => $schedule->id, 'status' => 'sent']);
    }

    public function test_schedule_email_blocked_when_no_assessor_email(): void
    {
        Mail::fake();
        $this->useSmsea();
        $user = User::factory()->staff()->create();
        $schedule = AssessmentSchedule::query()->create(['client_name' => 'Acme', 'service_name' => 'EIA', 'scheduled_from' => '2026-09-10', 'scheduled_to' => '2026-09-10', 'assessment_days' => 1, 'status' => 'planned']);
        $schedule->assessors()->sync([$this->assessor('NoEmail')->id]);

        $this->actingAs($user)->post(route('schedules.email', $schedule))->assertSessionHas('error');
        Mail::assertNothingSent();
    }

    // ---- REASSESSMENT REMINDERS -------------------------------------------

    private function completedDue(int $daysAhead, string $email = 'client@acme.test'): AssessmentSchedule
    {
        $this->useSmsea();
        $client = $this->client($email);

        return AssessmentSchedule::query()->create([
            'client_id' => $client->id, 'client_name' => $client->company_name, 'service_name' => 'EIA',
            'scheduled_from' => now()->subYear(), 'scheduled_to' => now()->subYear(), 'assessment_days' => 1,
            'status' => 'completed', 'completed_date' => now()->subYear(),
            'next_reassessment_date' => now()->addDays($daysAhead)->toDateString(),
            'reminder_enabled' => true,
        ]);
    }

    public function test_manual_reminder_sends_once_and_logs(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $schedule = $this->completedDue(20);

        $this->actingAs($admin)->post(route('reassessments.reminder', $schedule))->assertRedirect();
        Mail::assertSent(OperationalMail::class, fn ($m) => $m->hasTo('client@acme.test'));
        $this->assertNotNull($schedule->fresh()->reminder_sent_at);
        $this->assertDatabaseHas('document_email_deliveries', ['document_type' => 'reassessment_reminder', 'status' => 'sent']);
    }

    public function test_reminder_missing_email_is_reported(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $schedule = $this->completedDue(20, '');

        $this->actingAs($admin)->post(route('reassessments.reminder', $schedule))->assertSessionHas('error');
        Mail::assertNothingSent();
    }

    public function test_scheduler_command_sends_due_and_skips_already_sent(): void
    {
        Mail::fake();
        $due = $this->completedDue(20);          // within 30-day lead
        $notYet = $this->completedDue(200);      // outside lead window
        $alreadySent = $this->completedDue(10);
        $alreadySent->forceFill(['reminder_sent_at' => now()])->save();

        $this->artisan('smsea:send-reassessment-reminders')->assertSuccessful();

        Mail::assertSent(OperationalMail::class, 1);           // only the due one
        $this->assertNotNull($due->fresh()->reminder_sent_at);
        $this->assertNull($notYet->fresh()->reminder_sent_at);

        // Running again sends nothing (no duplicates).
        Mail::fake();
        $this->artisan('smsea:send-reassessment-reminders')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_reminders_respect_entity_disabled_setting(): void
    {
        Mail::fake();
        $this->useSmsea();
        $entityId = BusinessEntity::where('entity_code', 'SMSEA')->value('id');
        Setting::withoutGlobalScopes()->updateOrCreate(['business_entity_id' => $entityId], ['reassessment_reminder_enabled' => false, 'organization_name' => 'SMSEA']);
        $this->completedDue(10);

        $this->artisan('smsea:send-reassessment-reminders')->assertSuccessful();
        Mail::assertNothingSent();
    }
}
