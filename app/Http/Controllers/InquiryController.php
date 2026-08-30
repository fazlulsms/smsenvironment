<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ServiceInquiry;
use App\Support\InquiryServiceMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Office-side handling of public website inquiries. Read + light workflow only:
 * list, view, set status, and bridge into the EXISTING client/quotation flows by
 * prefilling their normal create forms (no client or quotation is created or
 * numbered here). All routes live under the authenticated /office area.
 */
class InquiryController extends Controller
{
    public function index(): View
    {
        return view('inquiries.index', [
            'inquiries' => ServiceInquiry::query()->latest()->paginate(20),
            'counts' => collect(ServiceInquiry::STATUSES)
                ->mapWithKeys(fn ($s) => [$s => ServiceInquiry::query()->where('status', $s)->count()]),
        ]);
    }

    public function show(ServiceInquiry $inquiry): View
    {
        $match = InquiryServiceMatcher::match($inquiry->service);

        // Suggest existing clients that look like this inquiry (exact email, or
        // a company-name match) so the user can reuse an existing client master.
        $suggested = Client::query()
            ->when($inquiry->email, fn ($q) => $q->orWhere('email', $inquiry->email))
            ->when($inquiry->company, fn ($q) => $q->orWhere('company_name', 'like', '%'.$inquiry->company.'%'))
            ->orderBy('company_name')
            ->limit(5)
            ->get(['id', 'company_name', 'email']);

        return view('inquiries.show', [
            'inquiry' => $inquiry,
            'matchedStandard' => $match,
            'matchedService' => $match?->name ?: $inquiry->service,
            'suggestedClients' => $suggested,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
            'statuses' => ServiceInquiry::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, ServiceInquiry $inquiry): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', ServiceInquiry::STATUSES)]]);
        $inquiry->update(['status' => $data['status']]);

        return back()->with('status', 'Inquiry marked as '.ucfirst($data['status']).'.');
    }

    /** Prefill the normal Client create form from the inquiry (never auto-created). */
    public function createClient(ServiceInquiry $inquiry): RedirectResponse
    {
        request()->session()->flashInput(array_filter([
            'company_name' => $inquiry->company ?: $inquiry->name,
            'contact_person' => $inquiry->name,
            'email' => $inquiry->email,
            'phone' => $inquiry->phone,
        ], fn ($v) => filled($v)));

        return redirect()->route('clients.create')
            ->with('status', 'Review and save this client, created from a website inquiry.');
    }

    /**
     * Bridge into the normal Quotation create form: resolve the client and map
     * the service server-side, then flash a prefill. No quotation is saved and no
     * number is consumed until the user saves through the normal workflow.
     */
    public function prepareQuotation(Request $request, ServiceInquiry $inquiry): RedirectResponse
    {
        $data = $request->validate(['client_id' => ['required', 'exists:clients,id']]);

        $standard = InquiryServiceMatcher::match($inquiry->service);
        $title = $standard?->name ?: ($inquiry->service ?: 'Environmental Services');

        $prefill = array_filter([
            'client_id' => $data['client_id'],
            'service_category_id' => $standard?->service_category_id,
            'charge_title' => $title,
            'standards' => $standard ? [$standard->id] : [],
            'items' => [['description' => $title]],
        ], fn ($v) => $v !== null && $v !== []);

        $request->session()->flashInput($prefill);

        // Light workflow touch: opening a quotation marks the inquiry reviewed.
        if ($inquiry->status === 'new') {
            $inquiry->update(['status' => 'reviewed']);
        }

        return redirect()->route('quotations.create')
            ->with('status', 'Quotation prefilled from website inquiry — set the amount and save to issue it.');
    }
}
