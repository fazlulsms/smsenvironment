@extends('layouts.app', ['title' => 'Assessors'])

@section('content')
<x-page-toolbar title="Assessors" subtitle="People who conduct assessments.">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('assessors.create') }}"><x-icon name="plus" :size="16" /> Add Assessor</a>
    </x-slot:actions>
</x-page-toolbar>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Designation</th><th>Email</th><th>Phone</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse ($assessors as $a)
                <tr>
                    <td><a class="row-title" href="{{ route('assessors.edit', $a) }}">{{ $a->name }}</a></td>
                    <td class="cell-sub">{{ $a->designation ?: '—' }}</td>
                    <td class="cell-sub">{{ $a->email ?: '—' }}</td>
                    <td class="cell-sub">{{ $a->phone ?: '—' }}</td>
                    <td><span class="badge-soft {{ $a->is_active ? 'b-ok' : 'b-neutral' }}">{{ $a->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td>
                        <div class="row-actions">
                            <a class="btn-icon" href="{{ route('assessors.edit', $a) }}" title="Edit"><x-icon name="edit" :size="16" /></a>
                            <form method="post" action="{{ route('assessors.active', $a) }}" data-confirm="{{ $a->is_active ? 'Deactivate this assessor?' : 'Activate this assessor?' }}">
                                @csrf @method('PATCH')
                                <button class="btn-icon" type="submit" title="{{ $a->is_active ? 'Deactivate' : 'Activate' }}"><x-icon name="{{ $a->is_active ? 'x' : 'check' }}" :size="16" /></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state icon="clients" title="No assessors yet" message="Add the people who carry out assessments.">
                    <a class="btn btn-primary btn-sm" href="{{ route('assessors.create') }}"><x-icon name="plus" :size="15" /> Add Assessor</a>
                </x-empty-state></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@if ($assessors->hasPages())<div class="mt-3">{{ $assessors->links() }}</div>@endif
@endsection
