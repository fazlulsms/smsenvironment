@extends('layouts.app', ['title' => 'Schedule'])

@section('content')
@php $sm = ['planned'=>'b-neutral','confirmed'=>'b-info','completed'=>'b-ok','cancelled'=>'b-danger'][$schedule->status] ?? 'b-neutral'; @endphp
<x-page-toolbar title="Assessment Schedule" subtitle="{{ $schedule->service_name }}">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('schedules.index') }}"><x-icon name="chevron-left" :size="15" /> All schedules</a>
    <x-slot:actions>
        @unless ($schedule->status === 'cancelled')
            <a class="btn btn-outline-secondary" href="{{ route('schedules.edit', $schedule) }}"><x-icon name="edit" :size="16" /> Edit</a>
            <form method="post" action="{{ route('schedules.email', $schedule) }}" data-confirm="Send the assignment email to the assessment team?">@csrf
                <button class="btn btn-outline-primary" type="submit"><x-icon name="send" :size="16" /> Send Schedule Email</button>
            </form>
            @unless ($schedule->isCompleted())
                <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#completeModal"><x-icon name="check" :size="16" /> Complete</button>
            @endunless
            @can('cancel-schedules')
                <form method="post" action="{{ route('schedules.cancel', $schedule) }}" data-confirm="Cancel this schedule?">@csrf
                    <button class="btn btn-outline-danger" type="submit"><x-icon name="x" :size="16" /> Cancel</button>
                </form>
            @endcan
        @endunless
    </x-slot:actions>
</x-page-toolbar>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">{{ $schedule->client_name ?: '—' }}</h2>
                <span class="badge-soft {{ $sm }}">{{ $schedule->statusLabel() }}</span>
            </div>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-secondary fw-normal">Service</dt><dd class="col-sm-8">{{ $schedule->service_name }}</dd>
                @if($schedule->site_name)<dt class="col-sm-4 text-secondary fw-normal">Facility / Site</dt><dd class="col-sm-8">{{ $schedule->site_name }}</dd>@endif
                @if($schedule->location)<dt class="col-sm-4 text-secondary fw-normal">Location</dt><dd class="col-sm-8">{{ $schedule->location }}</dd>@endif
                <dt class="col-sm-4 text-secondary fw-normal">Scheduled</dt><dd class="col-sm-8">{{ $schedule->scheduled_from?->format('d M Y') }} — {{ $schedule->scheduled_to?->format('d M Y') }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Assessment Days</dt><dd class="col-sm-8">{{ $schedule->assessment_days }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Assessor-Days</dt><dd class="col-sm-8">{{ $schedule->assessorDays() }} <span class="cell-sub">({{ $schedule->assessment_days }} × {{ $schedule->assessors->count() }})</span></dd>
                <dt class="col-sm-4 text-secondary fw-normal">Assessment Team</dt><dd class="col-sm-8">{{ $schedule->assessors->pluck('name')->implode(', ') ?: '—' }}</dd>
                @if($schedule->invoice)<dt class="col-sm-4 text-secondary fw-normal">Invoice</dt><dd class="col-sm-8"><a href="{{ route('proforma-invoices.show', $schedule->invoice) }}">{{ $schedule->invoice->number }}</a></dd>@endif
                @if($schedule->note)<dt class="col-sm-4 text-secondary fw-normal">Note</dt><dd class="col-sm-8">{{ $schedule->note }}</dd>@endif
            </dl>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <span class="eyebrow">Completion & Reassessment</span>
            @if ($schedule->isCompleted())
                <dl class="row mb-0 mt-2">
                    <dt class="col-sm-6 text-secondary fw-normal">Completed</dt><dd class="col-sm-6">{{ $schedule->completed_date?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-sm-6 text-secondary fw-normal">Next Reassessment</dt><dd class="col-sm-6">{{ $schedule->next_reassessment_date?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-sm-6 text-secondary fw-normal">Reminder</dt><dd class="col-sm-6">{{ $schedule->reminder_enabled ? 'Enabled' : 'Off' }} @if($schedule->reminder_sent_at)<span class="badge-soft b-ok">sent</span>@endif</dd>
                </dl>
            @else
                <p class="cell-sub mt-2 mb-0">Not completed yet. Use <b>Complete</b> to record the completion date and set the next reassessment date.</p>
            @endif
        </div></div>
    </div>
</div>

@if ($deliveries->isNotEmpty())
    <div class="card mt-3"><div class="card-body">
        <span class="eyebrow">Email History</span>
        <table class="table table-sm mt-2 mb-0"><thead><tr><th>When</th><th>To</th><th>Subject</th><th>Status</th></tr></thead><tbody>
        @foreach ($deliveries as $d)
            <tr><td class="cell-sub">{{ ($d->sent_at ?? $d->created_at)?->format('d M Y, g:i A') }}</td><td class="cell-sub">{{ $d->to_email }}</td><td class="cell-sub">{{ \Illuminate\Support\Str::limit($d->subject, 50) }}</td>
            <td>@if($d->status==='sent')<span class="badge-soft b-ok">Sent</span>@else<span class="badge-soft b-danger" title="{{ $d->error_summary }}">Failed</span>@endif</td></tr>
        @endforeach
        </tbody></table>
    </div></div>
@endif

{{-- Complete modal --}}
<div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="{{ route('schedules.complete', $schedule) }}" data-no-loading>
            @csrf
            <div class="modal-header"><h5 class="modal-title">Complete Assessment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Completion Date</label><input class="form-control" type="date" name="completed_date" id="cmp-date" value="{{ now()->toDateString() }}" required></div>
                    <div class="col-md-6"><label class="form-label">Next Reassessment</label><input class="form-control" type="date" name="next_reassessment_date" id="cmp-next" value="{{ now()->addYear()->toDateString() }}"></div>
                    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="reminder_enabled" value="1" checked> <span class="form-check-label">Enable client reassessment reminder</span></label></div>
                    <div class="col-12"><label class="form-label">Note</label><input class="form-control" name="note" value="{{ $schedule->note }}"></div>
                </div>
                <div class="form-text mt-2">Default next reassessment = completion + 1 year. Adjust for the applicable interval.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Mark Completed</button></div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('cmp-date')?.addEventListener('change', function () {
    var n = document.getElementById('cmp-next'); if (!this.value) return;
    var d = new Date(this.value); d.setFullYear(d.getFullYear() + 1); n.value = d.toISOString().slice(0, 10);
});
</script>
@endpush
@endsection
