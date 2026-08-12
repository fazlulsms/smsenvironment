<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class DocumentMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public string $attachmentData,
        public string $attachmentFilename,
        public ?string $fromAddress = null,
        public ?string $fromName = null,
        public ?string $replyToAddress = null,
    ) {}

    public function build(): self
    {
        $mail = $this->subject($this->subjectLine)
            ->view('emails.document')
            ->text('emails.document_text')
            ->attachData($this->attachmentData, $this->attachmentFilename, [
                'mime' => 'application/pdf',
            ]);

        if ($this->fromAddress) {
            $mail->from($this->fromAddress, $this->fromName);
        }

        if ($this->replyToAddress) {
            $mail->replyTo($this->replyToAddress);
        }

        return $mail;
    }
}
