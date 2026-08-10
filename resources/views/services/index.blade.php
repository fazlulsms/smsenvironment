@extends('layouts.app', ['title' => 'Services'])

@php $currency = \App\Models\Setting::current()->default_currency ?: 'BDT'; @endphp

@section('content')
<x-page-toolbar title="Service Library" subtitle="Standalone services, bundles and consolidated professional services.">
    <x-slot:actions>
        <form class="d-flex gap-2 flex-wrap" method="get">
            <div class="search-box">
                <x-icon name="search" :size="16" />
                <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search service">
            </div>
            <select class="form-select" style="max-width:170px" name="type" onchange="this.form.submit()">
                <option value="">All types</option>
                <option value="standalone" @selected(request('type')==='standalone')>Standalone</option>
                <option value="bundle" @selected(request('type')==='bundle')>Bundle / Package</option>
                <option value="consolidated" @selected(request('type')==='consolidated')>Consolidated</option>
            </select>
            <select class="form-select" style="max-width:140px" name="status" onchange="this.form.submit()">
                <option value="">Any status</option>
                <option value="active" @selected(request('status')==='active')>Active</option>
                <option value="inactive" @selected(request('status')==='inactive')>Inactive</option>
            </select>
            @if (request('search') || request('type') || request('status'))
                <a class="btn btn-outline-secondary" href="{{ route('services.index') }}" title="Clear"><x-icon name="x" :size="16" /></a>
            @endif
            <a class="btn btn-primary" href="{{ route('services.create') }}"><x-icon name="plus" :size="16" /> Add Service</a>
        </form>
    </x-slot:actions>
</x-page-toolbar>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Service</th><th>Type</th><th>Components</th><th>Unit</th><th class="num">Default Rate</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($services as $service)
                <tr>
                    <td>
                        <a class="row-title" href="{{ route('services.edit', $service) }}">{{ $service->name }}</a>
                        @if ($service->short_name)<div class="cell-sub">{{ $service->short_name }}</div>@endif
                    </td>
                    <td><x-service-type :type="$service->service_type" /></td>
                    <td class="cell-sub">
                        @if ($service->service_type === 'standalone') — @else {{ $service->components_count }} component{{ $service->components_count === 1 ? '' : 's' }} @endif
                    </td>
                    <td class="cell-sub">{{ $service->default_unit ?: '—' }}</td>
                    <td class="num money">@if ((float) $service->default_rate > 0)<span class="cur">{{ $currency }}</span>{{ number_format($service->default_rate, 2) }}@else <span class="cell-sub">—</span> @endif</td>
                    <td>
                        @if ($service->is_active)
                            <span class="badge-soft b-ok"><span class="dotmark"></span>Active</span>
                        @else
                            <span class="badge-soft b-neutral"><span class="dotmark"></span>Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="row-actions">
                            <a class="btn-icon" href="{{ route('services.edit', $service) }}" title="Edit"><x-icon name="edit" :size="16" /></a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">
                    <x-empty-state icon="services" title="No services yet"
                        message="Add your first environmental service, package or consolidated professional service.">
                        <a class="btn btn-primary btn-sm" href="{{ route('services.create') }}"><x-icon name="plus" :size="15" /> Add Service</a>
                    </x-empty-state>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($services->hasPages())
    <div class="mt-3">{{ $services->links() }}</div>
@endif
@endsection
