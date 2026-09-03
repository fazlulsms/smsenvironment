<?php

namespace App\Http\Controllers;

use App\Models\AssessmentSchedule;
use App\Models\Assessor;
use App\Models\Client;
use App\Models\DocumentEmailDelivery;
use App\Models\ProformaInvoice;
use App\Services\ScheduleMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssessmentScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'upcoming');
        $today = Carbon::today();

        $schedules = AssessmentSchedule::query()
            ->with(['assessors', 'client'])
            ->when($request->filled('assessor_id'), fn ($q) => $q->whereHas('assessors', fn ($a) => $a->where('assessors.id', $request->assessor_id)))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->client_id))
            ->when($request->filled('from') && $request->filled('to'), fn ($q) => $q->whereBetween('scheduled_from', [$request->from, $request->to]))
            ->when($filter === 'today', fn ($q) => $q->whereDate('scheduled_from', '<=', $today)->whereDate('scheduled_to', '>=', $today)->whereNotIn('status', ['cancelled']))
            ->when($filter === 'upcoming', fn ($q) => $q->whereDate('scheduled_from', '>=', $today)->whereNotIn('status', ['cancelled', 'completed']))
            ->when($filter === 'month', fn ($q) => $q->whereBetween('scheduled_from', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()]))
            ->when($filter === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->when($filter === 'cancelled', fn ($q) => $q->where('status', 'cancelled'))
            ->orderByDesc('scheduled_from')
            ->paginate(20)
            ->withQueryString();

        return view('schedules.index', [
            'schedules' => $schedules,
            'filter' => $filter,
            'assessors' => Assessor::query()->orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    public function create(Request $request): View
    {
        $invoice = $request->filled('invoice')
            ? ProformaInvoice::query()->with('items.service')->find($request->invoice)
            : null;

        return view('schedules.create', $this->formData(new AssessmentSchedule([
            'scheduled_from' => Carbon::today()->toDateString(),
            'scheduled_to' => Carbon::today()->toDateString(),
            'assessment_days' => 1,
            'reminder_enabled' => true,
        ]), $invoice));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSchedule($request);
        $client = Client::query()->find($data['client_id'] ?? null);

        $schedule = AssessmentSchedule::query()->create([
            'client_id' => $client?->id,
            'proforma_invoice_id' => $data['proforma_invoice_id'] ?? null,
            'client_name' => $client?->company_name ?? ($data['client_name'] ?? null),
            'service_name' => $data['service_name'],
            'site_name' => $data['site_name'] ?? null,
            'location' => $data['location'] ?? null,
            'scheduled_from' => $data['scheduled_from'],
            'scheduled_to' => $data['scheduled_to'],
            'assessment_days' => $data['assessment_days'],
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'reminder_enabled' => $request->boolean('reminder_enabled'),
            'created_by' => $request->user()->id,
        ]);
        $schedule->assessors()->sync($data['assessors'] ?? []);

        return redirect()->route('schedules.show', $schedule)->with('status', 'Assessment scheduled.');
    }

    public function show(AssessmentSchedule $schedule): View
    {
        $schedule->load(['assessors', 'client', 'invoice', 'creator']);

        return view('schedules.show', [
            'schedule' => $schedule,
            'deliveries' => DocumentEmailDelivery::query()
                ->where('document_type', 'assessment_schedule')->where('document_id', $schedule->id)
                ->with('sender')->latest('id')->get(),
        ]);
    }

    public function edit(AssessmentSchedule $schedule): View
    {
        return view('schedules.edit', $this->formData($schedule, $schedule->invoice));
    }

    public function update(Request $request, AssessmentSchedule $schedule): RedirectResponse
    {
        $data = $this->validateSchedule($request);
        $client = Client::query()->find($data['client_id'] ?? null);

        $schedule->update([
            'client_id' => $client?->id,
            'client_name' => $client?->company_name ?? ($data['client_name'] ?? $schedule->client_name),
            'service_name' => $data['service_name'],
            'site_name' => $data['site_name'] ?? null,
            'location' => $data['location'] ?? null,
            'scheduled_from' => $data['scheduled_from'],
            'scheduled_to' => $data['scheduled_to'],
            'assessment_days' => $data['assessment_days'],
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'reminder_enabled' => $request->boolean('reminder_enabled'),
        ]);
        $schedule->assessors()->sync($data['assessors'] ?? []);

        return redirect()->route('schedules.show', $schedule)->with('status', 'Schedule updated.');
    }

    public function complete(Request $request, AssessmentSchedule $schedule): RedirectResponse
    {
        $data = $request->validate([
            'completed_date' => ['required', 'date'],
            'next_reassessment_date' => ['nullable', 'date', 'after:completed_date'],
            'reminder_enabled' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $schedule->update([
            'status' => AssessmentSchedule::STATUS_COMPLETED,
            'completed_date' => $data['completed_date'],
            'next_reassessment_date' => $data['next_reassessment_date'] ?? null,
            'reminder_enabled' => $request->boolean('reminder_enabled'),
            // New cycle: clear any prior reminder marker.
            'reminder_sent_at' => null,
            'reminder_sent_by' => null,
            'note' => $data['note'] ?? $schedule->note,
        ]);

        return back()->with('status', 'Assessment marked completed.');
    }

    public function cancel(AssessmentSchedule $schedule): RedirectResponse
    {
        Gate::authorize('cancel-schedules');
        $schedule->update(['status' => AssessmentSchedule::STATUS_CANCELLED]);

        return back()->with('status', 'Schedule cancelled.');
    }

    public function email(AssessmentSchedule $schedule, ScheduleMailService $mailer): RedirectResponse
    {
        $schedule->loadMissing('assessors');
        if ($schedule->assessors->pluck('email')->filter()->isEmpty()) {
            return back()->with('error', 'No assigned assessor has an email address.');
        }

        try {
            $mailer->send($schedule, request()->user());
        } catch (\Throwable $e) {
            return back()->with('error', 'Email failed: '.$e->getMessage());
        }

        return back()->with('status', 'Schedule email sent to the assessment team.');
    }

    private function formData(AssessmentSchedule $schedule, ?ProformaInvoice $invoice): array
    {
        // Prefill from a Won invoice when arriving via "Schedule Assessment".
        if ($invoice && ! $schedule->exists) {
            $schedule->client_id = $invoice->client_id;
            $schedule->proforma_invoice_id = $invoice->id;
            $schedule->client_name = $invoice->client?->company_name ?? ($invoice->client_snapshot['company_name'] ?? null);
            $schedule->service_name = $invoice->items->first()?->service?->name ?? $invoice->charge_for;
            $schedule->site_name = $invoice->site_name;
        }

        return [
            'schedule' => $schedule,
            'assessorsList' => Assessor::query()->where('is_active', true)
                ->orWhereIn('id', $schedule->exists ? $schedule->assessors->pluck('id') : [])
                ->orderBy('name')->get(),
            'selectedAssessors' => $schedule->exists ? $schedule->assessors->pluck('id')->all() : [],
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
        ];
    }

    private function validateSchedule(Request $request): array
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'proforma_invoice_id' => ['nullable', 'exists:proforma_invoices,id'],
            'service_name' => ['required', 'string', 'max:255'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'scheduled_from' => ['required', 'date'],
            'scheduled_to' => ['required', 'date', 'after_or_equal:scheduled_from'],
            'assessment_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'status' => ['required', Rule::in(array_keys(AssessmentSchedule::STATUSES))],
            'assessors' => ['nullable', 'array'],
            'assessors.*' => ['integer', 'exists:assessors,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['assessment_days'] = ($data['assessment_days'] ?? null)
            ?: AssessmentSchedule::daysBetween($data['scheduled_from'], $data['scheduled_to']);

        return $data;
    }
}
