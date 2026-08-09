<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class DocumentMail extends Mailable
{
    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public string $attachmentData,
        public string $attachmentFilename
    ) {}

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.document')
            ->text('emails.document_text')
            ->attachData($this->attachmentData, $this->attachmentFilename, [
                'mime' => 'application/pdf',
            ]);
    }
}
