@extends('layouts.app', ['title' => 'Services'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Services</h1>
    <a class="btn btn-primary" href="{{ route('services.create') }}">Add Service</a>
</div>
<form class="row g-2 mb-3">
    <div class="col-md-5"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search service"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
</form>
<div class="panel">
    <table class="table align-middle mb-0">
        <thead><tr><th>Service</th><th>Unit</th><th class="text-end">Rate</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($services as $service)
            <tr>
                <td><a href="{{ route('services.edit', $service) }}">{{ $service->name }}</a><div class="text-secondary small">{{ $service->category }}</div></td>
                <td>{{ $service->default_unit }}</td>
                <td class="text-end">{{ number_format($service->default_rate, 2) }}</td>
                <td>{{ $service->is_active ? 'Active' : 'Inactive' }}</td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('services.edit', $service) }}">Edit</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $services->links() }}</div>
@endsection
