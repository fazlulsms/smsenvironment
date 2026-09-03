@extends('layouts.app', ['title' => 'New Schedule'])
@section('content')
<x-page-toolbar title="New Assessment Schedule"><a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('schedules.index') }}"><x-icon name="chevron-left" :size="15" /> All schedules</a></x-page-toolbar>
<form method="post" action="{{ route('schedules.store') }}" data-loading>@include('schedules._form')</form>
@endsection
