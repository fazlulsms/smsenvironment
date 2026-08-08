@extends('layouts.app', ['title' => 'Clients'])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Clients</h1>
    <a class="btn btn-primary" href="{{ route('clients.create') }}">Add Client</a>
</div>
<form class="row g-2 mb-3">
    <div class="col-md-5"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search company, contact, email"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
</form>
<div class="panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Company</th><th>Contact</th><th>Email</th><th>Phone</th><th></th></tr></thead>
            <tbody>
            @forelse ($clients as $client)
                <tr>
                    <td><a href="{{ route('clients.show', $client) }}">{{ $client->company_name }}</a><div class="text-secondary small">{{ $client->parent_company }}</div></td>
                    <td>{{ $client->contact_person }}<div class="text-secondary small">{{ $client->designation }}</div></td>
                    <td>{{ $client->email }}</td>
                    <td>{{ $client->phone }}</td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('clients.edit', $client) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-secondary">No clients found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $clients->links() }}</div>
@endsection
