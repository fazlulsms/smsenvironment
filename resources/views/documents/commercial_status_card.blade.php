@php
    $statusMeta = [
        'draft' => ['Draft', 'b-neutral'], 'sent' => ['Sent', 'b-info'],
        'won' => ['Won', 'b-ok'], 'lost' => ['Lost', 'b-danger'],
    ][$document->commercial_status] ?? ['Draft', 'b-neutral'];
@endphp
<div class="card h-100"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="eyebrow">Commercial Status</span>
        <span class="badge-soft {{ $statusMeta[1] }}">{{ $statusMeta[0] }}</span>
    </div>
    <form method="post" action="{{ $statusRoute }}" class="d-flex gap-2 flex-wrap align-items-end">
        @csrf @method('PATCH')
        <div class="flex-grow-1">
            <label class="form-label small text-muted mb-1">Set status</label>
            <select class="form-select form-select-sm" name="commercial_status" onchange="this.form.querySelector('[data-lost]').hidden = this.value !== 'lost'">
                @foreach (\App\Models\ProformaInvoice::COMMERCIAL_STATUSES as $val => $label)
                    <option value="{{ $val }}" @selected($document->commercial_status === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-primary btn-sm" type="submit"><x-icon name="check" :size="15" /> Update</button>
        <div class="w-100" data-lost @if($document->commercial_status !== 'lost') hidden @endif>
            <label class="form-label small text-muted mb-1 mt-2">Lost reason (optional)</label>
            <select class="form-select form-select-sm mb-2" name="lost_reason">
                <option value="">— none —</option>
                @foreach (\App\Models\ProformaInvoice::LOST_REASONS as $r)
                    <option value="{{ $r }}" @selected($document->lost_reason === $r)>{{ $r }}</option>
                @endforeach
            </select>
            <input class="form-control form-control-sm" name="lost_note" value="{{ $document->lost_note }}" placeholder="Lost note (optional)">
        </div>
    </form>
    <div class="d-flex gap-2 mt-3 flex-wrap">
        @if ($document->commercial_status === 'draft')
            <form method="post" action="{{ $markSentRoute }}">@csrf
                <button class="btn btn-outline-secondary btn-sm" type="submit"><x-icon name="send" :size="15" /> Mark as Sent</button>
            </form>
        @endif
        @if ($document->commercial_status === 'won')
            <a class="btn btn-outline-primary btn-sm" href="{{ route('schedules.create', $scheduleParam) }}"><x-icon name="clock" :size="15" /> Schedule Assessment</a>
        @endif
    </div>
</div></div>
