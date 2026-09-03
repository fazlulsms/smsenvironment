<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

/**
 * Simple plain-body operational email (no attachment) for schedule assignments
 * and reassessment reminders. Uses the same from/reply-to entity conventions as
 * DocumentMail.
 */
class OperationalMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public ?string $fromAddress = null,
        public ?string $fromName = null,
        public ?string $replyToAddress = null,
    ) {}

    public function build(): self
    {
        $mail = $this->subject($this->subjectLine)
            ->view('emails.operational', ['bodyText' => $this->bodyText])
            ->text('emails.operational_text', ['bodyText' => $this->bodyText]);

        if ($this->fromAddress) {
            $mail->from($this->fromAddress, $this->fromName);
        }
        if ($this->replyToAddress) {
            $mail->replyTo($this->replyToAddress);
        }

        return $mail;
    }
}
