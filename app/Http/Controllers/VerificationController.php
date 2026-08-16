<?php

namespace App\Http\Controllers;

use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Services\ProformaInvoiceVerificationService;
use App\Services\QuotationVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public document verification portal. The QR on every quotation / proforma
 * invoice links here; a recipient can also look a document up by its number. The
 * page recomputes the signature over our stored record and shows the authoritative
 * details, so a printed copy can be checked against source. Read-only, no auth,
 * and it never lists documents — you must know the exact code or number.
 */
class VerificationController extends Controller
{
    public function __construct(
        private ProformaInvoiceVerificationService $invoiceVerification,
        private QuotationVerificationService $quotationVerification,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return view('verify.index', ['query' => '', 'notFound' => false]);
        }

        // Look the number up across entities; documents are entity-scoped by default.
        $invoice = ProformaInvoice::query()->withoutGlobalScopes()->where('number', $query)->first();
        if ($invoice) {
            return redirect()->route('verify.show', $invoice->verification_id ?: $this->invoiceVerification->ensure($invoice)->verification_id);
        }

        $quotation = Quotation::query()->withoutGlobalScopes()->where('number', $query)->first();
        if ($quotation) {
            return redirect()->route('verify.show', $quotation->verification_id ?: $this->quotationVerification->ensure($quotation)->verification_id);
        }

        return view('verify.index', ['query' => $query, 'notFound' => true]);
    }

    public function show(string $code): View
    {
        $code = strtoupper(trim($code));

        if ($invoice = ProformaInvoice::query()->withoutGlobalScopes()->where('verification_id', $code)->first()) {
            return view('verify.show', $this->present('proforma_invoice', $invoice, $this->invoiceVerification));
        }

        if ($quotation = Quotation::query()->withoutGlobalScopes()->where('verification_id', $code)->first()) {
            return view('verify.show', $this->present('quotation', $quotation, $this->quotationVerification));
        }

        return view('verify.show', ['found' => false, 'code' => $code]);
    }

    /**
     * Assemble the display payload from the same canonical data the signature is
     * computed over, plus a recomputed-signature integrity check.
     */
    private function present(string $type, ProformaInvoice|Quotation $document, $service): array
    {
        $document->loadMissing('items');
        $data = $service->canonicalData($document);
        $settings = $document->settings_snapshot ?: [];

        $verified = hash_equals(
            (string) $document->verification_signature,
            $service->signature($document)
        );

        return [
            'found' => true,
            'verified' => $verified,
            'type' => $type,
            'typeLabel' => $type === 'proforma_invoice' ? 'Proforma Invoice' : 'Quotation',
            'entityName' => $settings['organization_name'] ?? 'SMS Environmental Alliance',
            'data' => $data,
            'document' => $document,
        ];
    }
}
