<?php

namespace App\Console\Commands;

use App\Models\AssessmentSchedule;
use App\Services\ReassessmentReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends one client reassessment reminder per completed assessment cycle, at the
 * per-entity configured lead time before the next reassessment date. Idempotent:
 * a schedule whose reminder has already been sent is skipped, so it is safe to
 * run every day. One failing email never stops the others.
 */
class SendReassessmentReminders extends Command
{
    protected $signature = 'smsea:send-reassessment-reminders';

    protected $description = 'Send due reassessment reminders to clients (one per cycle)';

    public function handle(ReassessmentReminderService $service): int
    {
        $today = Carbon::today();
        $eligible = 0;
        $sent = 0;
        $missingEmail = 0;
        $failed = 0;

        $candidates = AssessmentSchedule::query()
            ->withoutGlobalScopes()
            ->dueReminders()
            ->with('client')
            ->orderBy('next_reassessment_date')
            ->get();

        foreach ($candidates as $schedule) {
            if (! $service->remindersEnabled($schedule)) {
                continue;
            }
            // Only within the lead window (or overdue).
            $window = $today->copy()->addDays($service->leadDays($schedule));
            if ($schedule->next_reassessment_date->greaterThan($window)) {
                continue;
            }

            $eligible++;
            $result = $service->send($schedule, null, true);
            match ($result['status']) {
                'sent' => $sent++,
                'missing_email' => $missingEmail++,
                default => $failed++,
            };
        }

        $this->info("Eligible: {$eligible}");
        $this->info("Sent: {$sent}");
        $this->info("Skipped (missing email): {$missingEmail}");
        $this->info("Failed: {$failed}");

        return self::SUCCESS;
    }
}
