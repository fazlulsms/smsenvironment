@extends('layouts.app', ['title' => 'Reassessments'])

@section('content')
<x-page-toolbar title="Reassessments" subtitle="Upcoming and overdue periodic reassessments." />

<div class="d-flex gap-2 flex-wrap mb-3">
    @foreach (['all'=>'All','overdue'=>'Overdue','30'=>'Due 30 days','60'=>'Due 60 days','90'=>'Due 90 days'] as $key => $label)
        <a class="btn btn-sm {{ $bucket === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('reassessments.index', ['bucket' => $key]) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Client</th><th>Facility</th><th>Service</th><th>Last Assessment</th><th>Next Reassessment</th><th class="num">Days Left</th><th>Reminder</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($schedules as $s)
                @php
                    $daysLeft = $today->diffInDays($s->next_reassessment_date, false);
                    $left = $daysLeft < 0 ? ['Overdue '.abs($daysLeft).'d','b-danger'] : ($daysLeft <= 30 ? [$daysLeft.'d','b-warn'] : [$daysLeft.'d','b-neutral']);
                @endphp
                <tr>
                    <td><a class="row-title" href="{{ route('schedules.show', $s) }}">{{ $s->client_name ?: '—' }}</a></td>
                    <td class="cell-sub">{{ $s->site_name ?: '—' }}</td>
                    <td class="cell-sub">{{ $s->service_name }}</td>
                    <td class="cell-sub">{{ $s->completed_date?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $s->next_reassessment_date?->format('d M Y') }}</td>
                    <td class="num"><span class="badge-soft {{ $left[1] }}">{{ $left[0] }}</span></td>
                    <td>@if($s->reminder_sent_at)<span class="badge-soft b-ok" title="{{ $s->reminder_sent_at->format('d M Y') }}">Sent</span>@elseif(!$s->reminder_enabled)<span class="badge-soft b-neutral">Off</span>@else<span class="badge-soft b-warn">Pending</span>@endif</td>
                    <td class="text-end">
                        <form method="post" action="{{ route('reassessments.reminder', $s) }}" data-confirm="Send a reassessment reminder to the client now?">@csrf
                            <button class="btn btn-outline-primary btn-sm" type="submit" @disabled(blank($s->client?->email))><x-icon name="send" :size="14" /> {{ $s->reminder_sent_at ? 'Resend' : 'Send' }}</button>
                        </form>
                        @if(blank($s->client?->email))<div class="cell-sub text-danger">Missing email</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="clock" title="No reassessments due" message="Completed assessments with a next reassessment date appear here." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@if ($schedules->hasPages())<div class="mt-3">{{ $schedules->links() }}</div>@endif
@endsection
