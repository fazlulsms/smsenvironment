@extends('layouts.app', ['title' => 'All Entities'])

@php $currentId = app(\App\Support\CurrentEntity::class)->id(); @endphp

@section('content')
<x-page-toolbar title="All Entities Overview" subtitle="Document activity across every business entity. Select one to work inside it." />

<div class="row g-3">
    @foreach ($entities as $entity)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 {{ $entity->id === $currentId ? 'border-brand' : '' }}">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="dh-badge" style="width:44px;height:44px;border-radius:12px;background:var(--brand-050);color:var(--brand);font-size:15px">
                            {{ strtoupper(mb_substr($entity->short_name ?: $entity->name, 0, 2)) }}
                        </span>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2">
                                <strong>{{ $entity->name }}</strong>
                                @if ($entity->id === $currentId)<span class="badge-soft b-ok">Current</span>@endif
                                @unless ($entity->active)<span class="badge-soft b-neutral">Inactive</span>@endunless
                            </div>
                            <div class="cell-sub">{{ $entity->entity_code }}</div>
                        </div>
                    </div>
                    <div class="row text-center g-2 mb-3">
                        <div class="col-4"><div class="money">{{ $entity->stat_quotations }}</div><div class="cell-sub">Quotations</div></div>
                        <div class="col-4"><div class="money">{{ $entity->stat_invoices }}</div><div class="cell-sub">Invoices</div></div>
                        <div class="col-4"><div class="money">{{ $entity->stat_clients }}</div><div class="cell-sub">Clients</div></div>
                    </div>
                    <div class="cell-sub mb-3">
                        Quoted {{ $entity->default_currency }} {{ number_format($entity->stat_quoted_value, 0) }}
                        · Invoiced {{ $entity->default_currency }} {{ number_format($entity->stat_invoiced_value, 0) }}
                    </div>
                    @if ($entity->id === $currentId)
                        <a class="btn btn-outline-secondary w-100" href="{{ route('dashboard') }}">Open dashboard</a>
                    @elseif ($entity->active)
                        <form method="post" action="{{ route('entities.switch') }}">
                            @csrf
                            <input type="hidden" name="entity_id" value="{{ $entity->id }}">
                            <button class="btn btn-primary w-100" type="submit">Switch to {{ $entity->short_name ?: $entity->name }}</button>
                        </form>
                    @else
                        <button class="btn btn-outline-secondary w-100" disabled>Inactive</button>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
