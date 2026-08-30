New inquiry received from the SMS Environmental Alliance website.

Name:                {{ $inquiry->name }}
Company:             {{ $inquiry->company ?: '—' }}
Email:               {{ $inquiry->email }}
Phone:               {{ $inquiry->phone ?: '—' }}
Service Interested In: {{ $inquiry->service ?: '—' }}

Message:
{{ $inquiry->message ?: '—' }}

Submitted: {{ $inquiry->created_at?->format('d M Y, H:i') }}

—
This is an automated notification from the SMS Environmental Alliance website.
Reply directly to this email to respond to the sender.
