@extends('layouts.app', ['title' => 'Service Catalogue'])

@php
    $typeBadge = fn ($t) => match ($t) {
        'Standard' => 'b-service',
        'Program' => 'b-info',
        'Package' => 'b-ok',
        default => 'b-neutral',
    };
@endphp

@section('content')
<x-page-toolbar title="Service Catalogue" subtitle="The complete commercial catalogue — categories, standards, programs, services and packages.">
    <x-slot:actions>
        <form class="d-flex gap-2 flex-wrap" method="get">
            <div class="search-box">
                <x-icon name="search" :size="16" />
                <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search (ISO 9001, GRS, BSCI, Energy Audit…)">
            </div>
            <input type="hidden" name="category" value="{{ request('category') }}">
            <select class="form-select" style="max-width:160px" name="type" onchange="this.form.submit()">
                <option value="">All types</option>
                @foreach ($types as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>@endforeach
            </select>
            <select class="form-select" style="max-width:140px" name="status" onchange="this.form.submit()">
                <option value="">Any status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
            @if (request('search') || request('type') || request('status') || request('category'))
                <a class="btn btn-outline-secondary" href="{{ route('services.index') }}" title="Clear"><x-icon name="x" :size="16" /></a>
            @endif
            <a class="btn btn-primary" href="{{ route('catalogue-standards.create') }}"><x-icon name="plus" :size="16" /> New Catalogue Item</a>
        </form>
    </x-slot:actions>
</x-page-toolbar>

{{-- Category filter pills with live counts --}}
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="badge-soft {{ request('category') ? 'b-neutral' : 'b-service' }}" href="{{ request()->fullUrlWithQuery(['category' => null, 'page' => null]) }}">All <strong>{{ $total }}</strong></a>
    @foreach ($categoryCounts as $c)
        <a class="badge-soft {{ request('category') === $c['code'] ? 'b-service' : 'b-neutral' }}" href="{{ request()->fullUrlWithQuery(['category' => $c['code'], 'page' => null]) }}">{{ $c['name'] }} <strong>{{ $c['count'] }}</strong></a>
    @endforeach
</div>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Service / Standard</th><th>Category</th><th>Type</th><th>Components</th><th>Enabled For</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>
                        <a class="row-title" href="{{ $item['edit_url'] }}">{{ $item['name'] }}</a>
                        @if ($item['sub'])<div class="cell-sub">{{ $item['sub'] }}</div>@endif
                    </td>
                    <td class="cell-sub">{{ $item['category'] }}</td>
                    <td><span class="badge-soft {{ $typeBadge($item['type']) }}">{{ $item['type'] }}</span></td>
                    <td class="cell-sub">{{ $item['components'] > 0 ? $item['components'].' item'.($item['components'] === 1 ? '' : 's') : '—' }}</td>
                    <td>
                        @if (is_null($item['entities']))
                            <span class="badge-soft b-neutral" title="Available to every business entity">All entities</span>
                        @elseif (empty($item['entities']))
                            <span class="badge-soft b-warn">None</span>
                        @else
                            <div class="d-flex flex-wrap gap-1">
                                @foreach (array_slice($item['entities'], 0, 4) as $e)<span class="badge-soft b-neutral">{{ $e }}</span>@endforeach
                                @if (count($item['entities']) > 4)<span class="cell-sub">+{{ count($item['entities']) - 4 }}</span>@endif
                            </div>
                        @endif
                    </td>
                    <td>@if ($item['active'])<span class="badge-soft b-ok">Active</span>@else<span class="badge-soft b-warn">Inactive</span>@endif</td>
                    <td class="text-end">
                        <a class="btn btn-outline-secondary btn-sm" href="{{ $item['edit_url'] }}"><x-icon name="edit" :size="14" /> Edit</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-4">No catalogue items match your filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $items->links() }}</div>
@endsection
