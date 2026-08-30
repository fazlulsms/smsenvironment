@extends('layouts.app', ['title' => 'Clients'])

@section('content')
<x-page-toolbar title="Clients" subtitle="Your client directory.">
    <x-slot:actions>
        <form class="d-flex gap-2 flex-wrap" method="get">
            <div class="search-box">
                <x-icon name="search" :size="16" />
                <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search company, contact, email">
            </div>
            @if (request('search'))
                <a class="btn btn-outline-secondary" href="{{ route('clients.index') }}" title="Clear"><x-icon name="x" :size="16" /></a>
            @endif
        </form>
        <a class="btn btn-outline-primary" href="{{ route('clients.create') }}#smart-paste"><x-icon name="sparkles" :size="16" /> Smart Paste</a>
        <a class="btn btn-primary" href="{{ route('clients.create') }}"><x-icon name="plus" :size="16" /> Add Client</a>
    </x-slot:actions>
</x-page-toolbar>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th>Company</th><th>Contact</th><th>Email</th><th>Phone</th><th>Location</th><th class="num">Docs</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            @forelse ($clients as $client)
                @php $loc = collect([$client->city, $client->country])->filter()->implode(', '); @endphp
                <tr>
                    <td>
                        <a class="row-title" href="{{ route('clients.show', $client) }}">{{ $client->company_name }}</a>
                        @if ($client->parent_company)<div class="cell-sub">{{ $client->parent_company }}</div>@endif
                    </td>
                    <td>{{ $client->contact_person ?: '—' }}
                        @if ($client->designation)<div class="cell-sub">{{ $client->designation }}</div>@endif
                    </td>
                    <td class="cell-sub">{{ $client->email ?: '—' }}</td>
                    <td class="cell-sub">{{ $client->phone ?: '—' }}</td>
                    <td class="cell-sub">{{ $loc ?: '—' }}</td>
                    <td class="num">
                        @php $docs = $client->quotations_count + $client->proforma_invoices_count; @endphp
                        @if ($docs > 0)
                            <span class="badge-soft b-neutral" title="{{ $client->quotations_count }} quotations · {{ $client->proforma_invoices_count }} invoices">{{ $docs }}</span>
                        @else <span class="cell-sub">0</span> @endif
                    </td>
                    <td>
                        <div class="row-actions">
                            <a class="btn-icon" href="{{ route('clients.show', $client) }}" title="View"><x-icon name="eye" :size="16" /></a>
                            <a class="btn-icon" href="{{ route('quotations.create', ['client_id' => $client->id]) }}" title="New quotation for this client"><x-icon name="quotation" :size="16" /></a>
                            <a class="btn-icon" href="{{ route('proforma-invoices.create', ['client_id' => $client->id]) }}" title="New invoice for this client"><x-icon name="invoice" :size="16" /></a>
                            <div class="dropdown">
                                <button class="btn-icon" type="button" data-bs-toggle="dropdown" title="More"><x-icon name="dots" :size="16" /></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow">
                                    <li><a class="dropdown-item" href="{{ route('clients.edit', $client) }}"><x-icon name="edit" :size="15" class="me-2" />Edit</a></li>
                                    @can('delete', $client)
                                        <li><hr class="dropdown-divider"></li>
                                        @if ($docs > 0)
                                            <li><span class="dropdown-item disabled text-muted small">Has documents — can’t delete</span></li>
                                        @else
                                            <li>
                                                <form method="post" action="{{ route('clients.destroy', $client) }}" data-confirm="Delete {{ $client->company_name }}? This can’t be undone.">
                                                    @csrf @method('DELETE')
                                                    <button class="dropdown-item text-danger" type="submit"><x-icon name="trash" :size="15" class="me-2" />Delete</button>
                                                </form>
                                            </li>
                                        @endif
                                    @endcan
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">
                    <x-empty-state icon="clients" title="No clients yet"
                        message="Add a client manually, or paste messy contact details and let Smart Paste detect the fields.">
                        <div class="d-flex gap-2 justify-content-center">
                            <a class="btn btn-primary btn-sm" href="{{ route('clients.create') }}"><x-icon name="plus" :size="15" /> Add Client</a>
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('clients.create') }}#smart-paste"><x-icon name="sparkles" :size="15" /> Smart Paste</a>
                        </div>
                    </x-empty-state>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($clients->hasPages())
    <div class="mt-3">{{ $clients->links() }}</div>
@endif
@endsection
