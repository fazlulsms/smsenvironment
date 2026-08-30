<?php

namespace App\Mail;

use App\Models\ServiceInquiry;
use Illuminate\Mail\Mailable;

/**
 * Internal notification for a public "Request a Proposal" submission. Sent
 * synchronously through the application's configured mailer (From = the SMSEA
 * sender); Reply-To is set to the submitter so staff can respond directly. No
 * attachments, and it never creates any commercial record.
 */
class InquiryNotification extends Mailable
{
    public function __construct(
        public ServiceInquiry $inquiry,
        public ?string $replyToAddress = null,
    ) {}

    public function build(): self
    {
        $mail = $this->subject('New Website Inquiry — '.($this->inquiry->service ?: 'General'))
            ->text('emails.inquiry');

        if ($this->replyToAddress) {
            $mail->replyTo($this->replyToAddress, $this->inquiry->name);
        }

        return $mail;
    }
}
