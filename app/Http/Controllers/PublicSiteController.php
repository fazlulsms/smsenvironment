<?php

namespace App\Http\Controllers;

use App\Models\ServiceInquiry;
use App\Support\PublicSite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public SMS Environmental Alliance marketing website (served at the domain
 * root). It is entirely read-only and content-curated — it never touches clients,
 * quotations, invoices, banks or any internal Office data. The only write is a
 * public "Request a Proposal" lead, stored separately from commercial documents.
 */
class PublicSiteController extends Controller
{
    public function home(): View
    {
        return view('public.home', $this->shared());
    }

    public function services(): View
    {
        return view('public.services', $this->shared());
    }

    public function training(): View
    {
        return view('public.training', $this->shared());
    }

    public function about(): View
    {
        return view('public.about', $this->shared());
    }

    public function contact(): View
    {
        return view('public.contact', $this->shared());
    }

    public function privacy(): View
    {
        return view('public.privacy', $this->shared());
    }

    public function terms(): View
    {
        return view('public.terms', $this->shared());
    }

    public function storeInquiry(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:200'],
            'phone' => ['nullable', 'string', 'max:60'],
            'service' => ['nullable', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:5000'],
            // Honeypot: bots fill hidden fields; humans leave it empty.
            'website_url' => ['nullable', 'size:0'],
        ]);

        ServiceInquiry::query()->create([
            'name' => $data['name'],
            'company' => $data['company'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'service' => $data['service'] ?? null,
            'message' => $data['message'] ?? null,
            'source' => 'website',
        ]);

        return redirect()
            ->to(url()->previous().'#proposal')
            ->with('inquiry_status', 'Thank you — your request has been received. Our team will contact you shortly.');
    }

    private function shared(): array
    {
        return [
            'contact' => PublicSite::contact(),
            'families' => PublicSite::families(),
            'featured' => PublicSite::featured(),
            'industries' => PublicSite::industries(),
            'howWeWork' => PublicSite::howWeWork(),
            'trainingCategories' => PublicSite::trainingCategories(),
            'serviceOptions' => PublicSite::serviceOptions(),
            'testingScope' => PublicSite::environmentalTestingScope(),
        ];
    }
}
