@extends('layouts.app', ['title' => 'Login'])

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="panel p-4">
            <div class="d-flex align-items-center gap-2 mb-4">
                <span class="brand-mark">SE</span>
                <div>
                    <div class="fw-semibold">SMSEA Office</div>
                    <div class="text-secondary small">Internal document tool</div>
                </div>
            </div>
            <form method="post" action="{{ route('login.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password" required>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <button class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</div>
@endsection
