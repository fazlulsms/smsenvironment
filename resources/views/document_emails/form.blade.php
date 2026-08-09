@extends('layouts.app', ['title' => $title])

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h3 mb-0">{{ $title }}</h1>
        <div class="text-secondary">{{ $document->number }} - {{ $document->client_snapshot['company_name'] ?? $document->client?->company_name }}</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ $backRoute }}">Back</a>
</div>

<form class="panel p-3" method="post" action="{{ $sendRoute }}" id="emailForm">
    @csrf
    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label">To</label>
            <input class="form-control" type="email" name="to" value="{{ old('to', $draft['to']) }}" required>
        </div>
        <div class="col-md-5">
            <label class="form-label">CC</label>
            <input class="form-control" name="cc" value="{{ old('cc', $draft['cc']) }}" placeholder="finance@example.com, coordinator@example.com">
        </div>
        <div class="col-12">
            <label class="form-label">Subject</label>
            <input class="form-control" name="subject" value="{{ old('subject', $draft['subject']) }}" required>
        </div>
        <div class="col-12">
            <label class="form-label">Message</label>
            <textarea class="form-control" rows="13" name="message" required>{{ old('message', $draft['message']) }}</textarea>
        </div>
    </div>

    <div class="border-top mt-4 pt-3">
        <div class="muted-label mb-2">Send Confirmation</div>
        <div class="row g-2 small">
            <div class="col-md-4"><strong>To:</strong> <span id="confirmTo">{{ old('to', $draft['to']) ?: 'Required before sending' }}</span></div>
            <div class="col-md-4"><strong>CC:</strong> <span id="confirmCc">{{ old('cc', $draft['cc']) ?: 'None' }}</span></div>
            <div class="col-md-4"><strong>Attachment:</strong> {{ $draft['attachment_filename'] }}</div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" id="sendButton">Send Email</button>
        <a class="btn btn-outline-secondary" href="{{ $backRoute }}">Cancel</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
const form = document.getElementById('emailForm');
const toInput = form.querySelector('[name="to"]');
const ccInput = form.querySelector('[name="cc"]');
const sendButton = document.getElementById('sendButton');
const confirmTo = document.getElementById('confirmTo');
const confirmCc = document.getElementById('confirmCc');

function refreshConfirmation() {
    confirmTo.textContent = toInput.value || 'Required before sending';
    confirmCc.textContent = ccInput.value || 'None';
}

toInput.addEventListener('input', refreshConfirmation);
ccInput.addEventListener('input', refreshConfirmation);
form.addEventListener('submit', () => {
    sendButton.disabled = true;
    sendButton.textContent = 'Sending...';
});
</script>
@endpush
