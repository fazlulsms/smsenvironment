<?php

namespace App\Http\Controllers;

use App\Models\AssessmentSchedule;
use App\Services\ReassessmentReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReassessmentController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();
        $bucket = $request->input('bucket', 'all');

        $schedules = AssessmentSchedule::query()
            ->with(['client', 'assessors'])
            ->whereNotNull('next_reassessment_date')
            ->where('status', AssessmentSchedule::STATUS_COMPLETED)
            ->when($bucket === 'overdue', fn ($q) => $q->whereDate('next_reassessment_date', '<', $today))
            ->when($bucket === '30', fn ($q) => $q->whereBetween('next_reassessment_date', [$today, $today->copy()->addDays(30)]))
            ->when($bucket === '60', fn ($q) => $q->whereBetween('next_reassessment_date', [$today, $today->copy()->addDays(60)]))
            ->when($bucket === '90', fn ($q) => $q->whereBetween('next_reassessment_date', [$today, $today->copy()->addDays(90)]))
            ->orderBy('next_reassessment_date')
            ->paginate(25)
            ->withQueryString();

        return view('reassessments.index', [
            'schedules' => $schedules,
            'bucket' => $bucket,
            'today' => $today,
        ]);
    }

    public function sendReminder(AssessmentSchedule $schedule, ReassessmentReminderService $service): RedirectResponse
    {
        $result = $service->send($schedule, request()->user());

        return match ($result['status']) {
            'sent' => back()->with('status', 'Reassessment reminder sent.'),
            'missing_email' => back()->with('error', 'Client has no email address — reminder not sent.'),
            default => back()->with('error', 'Reminder failed: '.($result['reason'] ?? 'unknown error')),
        };
    }
}
