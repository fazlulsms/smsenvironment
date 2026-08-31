@extends('layouts.app', ['title' => 'Business Entities'])

@php $currentId = app(\App\Support\CurrentEntity::class)->id(); @endphp

@section('content')
<x-page-toolbar title="Business Entities" subtitle="Identity, branding colours and logo for each company workspace.">
    <x-slot:actions>
        <a class="btn btn-outline-secondary" href="{{ route('entities.overview') }}"><x-icon name="dashboard" :size="16" /> Overview</a>
    </x-slot:actions>
</x-page-toolbar>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Entity</th><th>Code</th><th>Theme</th><th>Currency</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @foreach ($entities as $entity)
                @php $theme = $entity->theme(); @endphp
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="dh-badge" style="width:34px;height:34px;border-radius:9px;font-size:12px;overflow:hidden;background:{{ $theme['primary'] }};color:#fff">
                                @if ($entity->logoUrl())<img src="{{ $entity->logoUrl() }}" alt="" style="width:100%;height:100%;object-fit:contain;background:#fff">@else {{ strtoupper(mb_substr($entity->short_name ?: $entity->name, 0, 2)) }} @endif
                            </span>
                            <div>
                                <span class="row-title">{{ $entity->name }}</span>
                                @if ($entity->id === $currentId)<span class="badge-soft b-ok ms-1">Current</span>@endif
                            </div>
                        </div>
                    </td>
                    <td class="cell-sub">{{ $entity->entity_code }}</td>
                    <td>
                        <div class="d-flex gap-1">
                            <span title="Primary" style="width:18px;height:18px;border-radius:5px;background:{{ $theme['primary'] }}"></span>
                            <span title="Secondary" style="width:18px;height:18px;border-radius:5px;background:{{ $theme['secondary'] }}"></span>
                            <span title="Accent" style="width:18px;height:18px;border-radius:5px;background:{{ $theme['accent'] }}"></span>
                        </div>
                    </td>
                    <td class="cell-sub">{{ $entity->default_currency }}</td>
                    <td>@if ($entity->active)<span class="badge-soft b-ok"><span class="dotmark"></span>Active</span>@else<span class="badge-soft b-neutral"><span class="dotmark"></span>Inactive</span>@endif</td>
                    <td>
                        <div class="row-actions">
                            @if ($entity->id !== $currentId && $entity->active)
                                <form method="post" action="{{ route('entities.switch') }}">@csrf<input type="hidden" name="entity_id" value="{{ $entity->id }}">
                                    <button class="btn btn-outline-secondary btn-sm" type="submit">Switch</button>
                                </form>
                            @endif
                            <a class="btn-icon" href="{{ route('entities.edit', $entity) }}" title="Edit"><x-icon name="edit" :size="16" /></a>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
