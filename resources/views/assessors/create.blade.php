@extends('layouts.app', ['title' => 'Add Assessor'])
@section('content')
<x-page-toolbar title="Add Assessor"><a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('assessors.index') }}"><x-icon name="chevron-left" :size="15" /> All assessors</a></x-page-toolbar>
<form method="post" action="{{ route('assessors.store') }}" data-loading>@include('assessors._form')</form>
@endsection
