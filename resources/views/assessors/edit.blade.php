@extends('layouts.app', ['title' => 'Edit Assessor'])
@section('content')
<x-page-toolbar title="Edit Assessor"><a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('assessors.index') }}"><x-icon name="chevron-left" :size="15" /> All assessors</a></x-page-toolbar>
<form method="post" action="{{ route('assessors.update', $assessor) }}" data-loading>@method('PUT')@include('assessors._form')</form>
@endsection
