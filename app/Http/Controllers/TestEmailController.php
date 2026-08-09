<?php

namespace App\Http\Controllers;

use App\Mail\TestConfigurationMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TestEmailController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        try {
            Mail::to($data['test_email'])->send(new TestConfigurationMail);
        } catch (Throwable) {
            return redirect()->route('settings.edit')
                ->withInput()
                ->withErrors(['test_email' => 'Test email could not be sent. Please check SMTP settings and try again.']);
        }

        return redirect()->route('settings.edit')->with('status', "Test email sent successfully to {$data['test_email']}.");
    }
}
