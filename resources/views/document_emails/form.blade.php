@extends('layouts.app', ['title' => $title])

@section('content')
<x-page-toolbar title="{{ $title }}" subtitle="{{ $document->number }} · {{ $document->client_snapshot['company_name'] ?? $document->client?->company_name }}">
    <a class="btn btn-outline-secondary btn-sm mb-1" href="{{ $backRoute }}"><x-icon name="chevron-left" :size="15" /> Back</a>
</x-page-toolbar>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-head"><h2>Compose</h2></div>
            <div class="card-body">
                <form method="post" action="{{ $sendRoute }}" id="emailForm">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label">To</label>
                            <input class="form-control" type="email" name="to" value="{{ old('to', $draft['to']) }}" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">CC <span class="text-secondary fw-normal">(optional)</span></label>
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
                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a class="btn btn-outline-secondary" href="{{ $backRoute }}">Cancel</a>
                        <button class="btn btn-primary" id="sendButton" type="submit"><x-icon name="send" :size="16" /> Send Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-head"><h2>Send Summary</h2></div>
            <div class="card-body">
                <dl class="kv mb-0">
                    <dt>To</dt><dd id="confirmTo">{{ old('to', $draft['to']) ?: 'Required before sending' }}</dd>
                    <dt>CC</dt><dd id="confirmCc">{{ old('cc', $draft['cc']) ?: 'None' }}</dd>
                    <dt>Attachment</dt><dd class="d-flex align-items-start gap-1"><x-icon name="download" :size="15" class="mt-1 text-secondary" /><span>{{ $draft['attachment_filename'] }}</span></dd>
                </dl>
                <div class="alert alert-light border mt-3 mb-0 small text-secondary">
                    The saved document PDF is attached automatically and matches what you previewed.
                </div>
            </div>
        </div>
    </div>
</div>
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
    sendButton.classList.add('is-loading');
    sendButton.disabled = true;
});
</script>
@endpush
