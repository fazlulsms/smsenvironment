@extends('layouts.app', ['title' => 'Edit Schedule'])
@section('content')
<x-page-toolbar title="Edit Schedule"><a class="btn btn-outline-secondary btn-sm mb-1" href="{{ route('schedules.show', $schedule) }}"><x-icon name="chevron-left" :size="15" /> Back</a></x-page-toolbar>
<form method="post" action="{{ route('schedules.update', $schedule) }}" data-loading>@method('PUT')@include('schedules._form')</form>
@endsection
