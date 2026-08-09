<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class TestConfigurationMail extends Mailable
{
    public function build(): self
    {
        return $this->subject('SMSEA Office email configuration test')
            ->text('emails.test_configuration_text');
    }
}
