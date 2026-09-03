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
 * Sends the assessment-assignment email to the assigned assessors and logs it
 * to the shared email delivery history (document_type = assessment_schedule).
 */
class ScheduleMailService
{
    public function send(AssessmentSchedule $schedule, User $sender): DocumentEmailDelivery
    {
        $schedule->loadMissing('assessors');
        $recipients = $schedule->assessors->pluck('email')->filter()->values()->all();

        $to = $recipients[0] ?? null;
        $cc = array_slice($recipients, 1);
        $subject = 'Assessment Assignment — '.($schedule->client_name ?: 'Assessment').' ('.$schedule->scheduled_from?->format('d M Y').')';
        $body = $this->body($schedule);

        $delivery = DocumentEmailDelivery::query()->create([
            'business_entity_id' => $schedule->business_entity_id,
            'document_type' => 'assessment_schedule',
            'document_id' => $schedule->id,
            'to_email' => $to ?: '(no assessor email)',
            'cc_emails' => $cc,
            'subject' => $subject,
            'body_snapshot' => $body,
            'sent_by' => $sender->id,
            'status' => 'failed',
        ]);

        if (! $to) {
            $delivery->update(['error_summary' => 'No assigned assessor has an email address.']);

            return $delivery->fresh();
        }

        try {
            $account = EntityMailer::account($schedule->business_entity_id);
            EntityMailer::mailer($account)->to($to)->cc($cc)->send(new OperationalMail(
                subjectLine: $subject,
                bodyText: $body,
                fromAddress: $account?->from_address,
                fromName: $account?->from_name,
                replyToAddress: $account?->reply_to,
            ));

            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'error_summary' => null]);
        } catch (Throwable $e) {
            $delivery->update(['status' => 'failed', 'error_summary' => Str::limit($e->getMessage(), 500)]);
            throw $e;
        }

        return $delivery->fresh();
    }

    private function body(AssessmentSchedule $schedule): string
    {
        $org = Setting::current()->organization_name ?: 'SMS Environmental Alliance';
        $team = $schedule->assessors->pluck('name')->implode(', ');
        $dates = $schedule->scheduled_from?->format('d M Y')
            .($schedule->scheduled_to && ! $schedule->scheduled_to->equalTo($schedule->scheduled_from)
                ? ' to '.$schedule->scheduled_to->format('d M Y') : '');

        return "Dear Assessor,\n\n"
            ."We have planned to conduct the {$schedule->service_name} for {$schedule->client_name} "
            .($schedule->site_name ? "({$schedule->site_name}) " : '')
            ."from {$dates}.\n\n"
            .'You have been assigned as a member of the assessment team. The assessment may include, as applicable, '
            .'document and record review, data collection and review, site observations, and discussions with responsible '
            ."personnel relevant to the agreed assessment scope.\n\n"
            ."Assessment details:\n"
            ."Client: {$schedule->client_name}\n"
            .($schedule->site_name ? "Facility/Site: {$schedule->site_name}\n" : '')
            ."Service: {$schedule->service_name}\n"
            ."Scheduled Date(s): {$dates}\n"
            ."Assessment Days: {$schedule->assessment_days}\n"
            ."Assessment Team: {$team}\n\n"
            ."Please review the schedule and contact us if any clarification is required.\n\n"
            ."Thank you.\n\n{$org}";
    }
}
