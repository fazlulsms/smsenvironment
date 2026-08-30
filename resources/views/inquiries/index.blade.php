@extends('layouts.app', ['title' => 'Website Inquiries'])

@section('content')
<x-page-toolbar title="Website Inquiries" subtitle="Proposal requests submitted through the public website." />

@if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

@php
    $badge = fn ($s) => match ($s) {
        'new' => 'b-warn', 'reviewed' => 'b-neutral', 'converted' => 'b-ok', default => 'b-neutral',
    };
@endphp

<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach ($counts as $status => $count)
        <span class="badge-soft {{ $badge($status) }}">{{ ucfirst($status) }}: {{ $count }}</span>
    @endforeach
</div>

<div class="card"><div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Received</th><th>Name</th><th>Company</th><th>Service</th><th>Contact</th><th>Status</th><th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($inquiries as $inquiry)
                    <tr>
                        <td class="text-nowrap">{{ $inquiry->created_at?->format('d M Y, H:i') }}</td>
                        <td>{{ $inquiry->name }}</td>
                        <td>{{ $inquiry->company ?: '—' }}</td>
                        <td>{{ $inquiry->service ?: '—' }}</td>
                        <td class="small">
                            <div>{{ $inquiry->email }}</div>
                            @if ($inquiry->phone)<div class="text-secondary">{{ $inquiry->phone }}</div>@endif
                        </td>
                        <td><span class="badge-soft {{ $badge($inquiry->status) }}">{{ $inquiry->statusLabel() }}</span></td>
                        <td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="{{ route('inquiries.show', $inquiry) }}"><x-icon name="eye" :size="15" /> View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">No website inquiries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div></div>

<div class="mt-3">{{ $inquiries->links() }}</div>
@endsection
