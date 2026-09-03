@extends('layouts.app', ['title' => 'Assessment Schedule'])

@section('content')
<x-page-toolbar title="Assessment Schedule" subtitle="Who conducts what, for whom, and when.">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('schedules.create') }}"><x-icon name="plus" :size="16" /> New Schedule</a>
    </x-slot:actions>
</x-page-toolbar>

<div class="d-flex gap-2 flex-wrap mb-3">
    @foreach (['today' => 'Today', 'upcoming' => 'Upcoming', 'month' => 'This Month', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
        <a class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('schedules.index', ['filter' => $key]) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Date(s)</th><th>Client</th><th>Service</th><th>Assessors</th><th class="num">Days</th><th class="num">Asr-Days</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($schedules as $s)
                @php $sm = ['planned'=>'b-neutral','confirmed'=>'b-info','completed'=>'b-ok','cancelled'=>'b-danger'][$s->status] ?? 'b-neutral'; @endphp
                <tr>
                    <td>
                        <a class="row-title" href="{{ route('schedules.show', $s) }}">{{ $s->scheduled_from?->format('d M') }}@if($s->scheduled_to && !$s->scheduled_to->equalTo($s->scheduled_from))–{{ $s->scheduled_to->format('d M Y') }}@else {{ $s->scheduled_from?->format('Y') }}@endif</a>
                    </td>
                    <td>{{ $s->client_name ?: '—' }}@if($s->site_name)<div class="cell-sub">{{ $s->site_name }}</div>@endif</td>
                    <td class="cell-sub">{{ $s->service_name }}</td>
                    <td class="cell-sub">{{ $s->assessors->pluck('name')->implode(', ') ?: '—' }}</td>
                    <td class="num">{{ $s->assessment_days }}</td>
                    <td class="num">{{ $s->assessorDays() }}</td>
                    <td><span class="badge-soft {{ $sm }}">{{ $s->statusLabel() }}</span></td>
                    <td class="text-end"><a class="btn-icon" href="{{ route('schedules.show', $s) }}" title="View"><x-icon name="eye" :size="16" /></a></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="invoice" title="No schedules" message="Plan an assessment to get started.">
                    <a class="btn btn-primary btn-sm" href="{{ route('schedules.create') }}"><x-icon name="plus" :size="15" /> New Schedule</a>
                </x-empty-state></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@if ($schedules->hasPages())<div class="mt-3">{{ $schedules->links() }}</div>@endif
@endsection
