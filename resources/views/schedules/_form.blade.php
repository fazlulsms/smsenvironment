@csrf
@if ($schedule->proforma_invoice_id)<input type="hidden" name="proforma_invoice_id" value="{{ $schedule->proforma_invoice_id }}">@endif
<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="invoice" /></span><div><div class="fs-t">Assessment</div><div class="fs-s">Client, service, site and dates.</div></div></div>
    <div class="fs-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Client</label>
                <select class="form-select" name="client_id">
                    <option value="">— select client —</option>
                    @foreach ($clients as $c)<option value="{{ $c->id }}" @selected(old('client_id', $schedule->client_id) == $c->id)>{{ $c->company_name }}</option>@endforeach
                </select>
                <div class="form-text">Or leave blank and type a name below (for ad-hoc engagements).</div>
                <input class="form-control mt-1" name="client_name" value="{{ old('client_name', $schedule->client_name) }}" placeholder="Client name (if not in list)">
            </div>
            <div class="col-md-6"><label class="form-label">Service / Assessment</label><input class="form-control" name="service_name" value="{{ old('service_name', $schedule->service_name) }}" required></div>
            <div class="col-md-6"><label class="form-label">Facility / Site</label><input class="form-control" name="site_name" value="{{ old('site_name', $schedule->site_name) }}"></div>
            <div class="col-md-6"><label class="form-label">Location</label><input class="form-control" name="location" value="{{ old('location', $schedule->location) }}"></div>
            <div class="col-md-4"><label class="form-label">From Date</label><input class="form-control" type="date" name="scheduled_from" value="{{ old('scheduled_from', optional($schedule->scheduled_from)->toDateString() ?: $schedule->scheduled_from) }}" data-sched-from required></div>
            <div class="col-md-4"><label class="form-label">To Date</label><input class="form-control" type="date" name="scheduled_to" value="{{ old('scheduled_to', optional($schedule->scheduled_to)->toDateString() ?: $schedule->scheduled_to) }}" data-sched-to required></div>
            <div class="col-md-4"><label class="form-label">Assessment Days</label><input class="form-control" type="number" min="1" max="60" name="assessment_days" value="{{ old('assessment_days', $schedule->assessment_days ?: 1) }}" data-sched-days></div>
            <div class="col-md-6">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    @foreach (\App\Models\AssessmentSchedule::STATUSES as $val => $label)
                        @if ($val !== 'completed' || $schedule->status === 'completed')
                            <option value="{{ $val }}" @selected(old('status', $schedule->status ?: 'planned') === $val)>{{ $label }}</option>
                        @endif
                    @endforeach
                </select>
                <div class="form-text">Mark Completed from the schedule page (to set the reassessment date).</div>
            </div>
            <div class="col-12"><label class="form-label">Note</label><input class="form-control" name="note" value="{{ old('note', $schedule->note) }}"></div>
        </div>
    </div>
</div>

<div class="form-section">
    <div class="fs-head"><span class="fs-ico"><x-icon name="clients" /></span><div><div class="fs-t">Assessment Team</div><div class="fs-s">Select one or more assessors — assessor-days are calculated from days × team size.</div></div></div>
    <div class="fs-body">
        <div class="row g-2">
            @forelse ($assessorsList as $a)
                <div class="col-md-4"><label class="form-check">
                    <input class="form-check-input" type="checkbox" name="assessors[]" value="{{ $a->id }}" @checked(in_array($a->id, old('assessors', $selectedAssessors)))>
                    <span class="form-check-label">{{ $a->name }}@if(!$a->is_active) <span class="badge-soft b-neutral">inactive</span>@endif</span>
                </label></div>
            @empty
                <div class="col-12 cell-sub">No assessors yet — <a href="{{ route('assessors.create') }}">add one</a>.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="d-flex gap-2 justify-content-end">
    <a class="btn btn-outline-secondary" href="{{ route('schedules.index') }}">Cancel</a>
    <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ $schedule->exists ? 'Update' : 'Save' }} Schedule</button>
</div>

@push('scripts')
<script>
(function () {
    var from = document.querySelector('[data-sched-from]'), to = document.querySelector('[data-sched-to]'), days = document.querySelector('[data-sched-days]');
    function calc() {
        if (!from.value || !to.value) return;
        var d = Math.round((new Date(to.value) - new Date(from.value)) / 86400000) + 1;
        if (d >= 1) days.value = d;
    }
    if (from && to && days) { from.addEventListener('change', calc); to.addEventListener('change', calc); }
})();
</script>
@endpush
