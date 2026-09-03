<?php

namespace App\Services;

use App\Mail\OperationalMail;
use App\Models\AssessmentSchedule;
use App\Models\DocumentEmailDelivery;
use App\Models\Setting;
use App\Models\User;
use App\Support\EntityMailer;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds and sends the client reassessment reminder, logs it, and marks the
 * schedule so the same reminder is never sent twice in a cycle.
 */
class ReassessmentReminderService
{
    /** Resolve the reminder-lead setting for the schedule's entity. */
    public function leadDays(AssessmentSchedule $schedule): int
    {
        return (int) ($this->entitySetting($schedule)?->reassessment_reminder_lead_days ?? 30);
    }

    public function remindersEnabled(AssessmentSchedule $schedule): bool
    {
        return (bool) ($this->entitySetting($schedule)?->reassessment_reminder_enabled ?? true);
    }

    /**
     * @return array{status:string, delivery:?DocumentEmailDelivery, reason:?string}
     */
    public function send(AssessmentSchedule $schedule, ?User $sender = null, bool $automatic = false): array
    {
        $schedule->loadMissing('client');
        $email = $schedule->client?->email ?: ($schedule->client_name ? null : null);

        if (blank($email)) {
            return ['status' => 'missing_email', 'delivery' => null, 'reason' => 'Client has no email address.'];
        }

        $org = Setting::current()->organization_name ?: 'SMS Environmental Alliance';
        $subject = 'Upcoming Environmental Reassessment Reminder — '.($schedule->site_name ?: $schedule->client_name);
        $body = $this->body($schedule, $org);

        $delivery = DocumentEmailDelivery::query()->create([
            'business_entity_id' => $schedule->business_entity_id,
            'document_type' => 'reassessment_reminder',
            'document_id' => $schedule->id,
            'to_email' => $email,
            'cc_emails' => [],
            'subject' => $subject,
            'body_snapshot' => $body,
            'sent_by' => $sender?->id,
            'status' => 'failed',
        ]);

        try {
            $account = EntityMailer::account($schedule->business_entity_id);
            EntityMailer::mailer($account)->to($email)->send(new OperationalMail(
                subjectLine: $subject,
                bodyText: $body,
                fromAddress: $account?->from_address,
                fromName: $account?->from_name,
                replyToAddress: $account?->reply_to,
            ));

            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error_summary' => null]);
            $schedule->forceFill([
                'reminder_sent_at' => now(),
                'reminder_sent_by' => $sender?->id,
            ])->save();

            return ['status' => 'sent', 'delivery' => $delivery->fresh(), 'reason' => null];
        } catch (Throwable $e) {
            $delivery->update(['status' => 'failed', 'error_summary' => Str::limit($e->getMessage(), 500)]);

            return ['status' => 'failed', 'delivery' => $delivery->fresh(), 'reason' => $e->getMessage()];
        }
    }

    private function body(AssessmentSchedule $schedule, string $org): string
    {
        $due = $schedule->next_reassessment_date?->format('d M Y');
        $facility = $schedule->site_name ?: $schedule->client_name;

        return "Dear Sir,\n\n"
            ."Greetings from {$org}.\n\n"
            ."This is a reminder that the next periodic {$schedule->service_name} for {$facility} is approaching and is "
            ."currently due around {$due}.\n\n"
            .'We recommend arranging the reassessment in advance to support continuity of applicable environmental '
            ."monitoring and compliance requirements.\n\n"
            ."Please let us know your preferred schedule, and our team will coordinate accordingly.\n\n"
            ."Thank you.\n\n{$org}";
    }

    private function entitySetting(AssessmentSchedule $schedule): ?Setting
    {
        return Setting::withoutGlobalScopes()->where('business_entity_id', $schedule->business_entity_id)->first();
    }
}
