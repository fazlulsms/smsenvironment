<?php

namespace App\Http\Controllers;

use App\Models\DocumentEmailDelivery;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Services\DocumentEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class DocumentEmailController extends Controller
{
    public function index(Request $request): View
    {
        $deliveries = DocumentEmailDelivery::query()
            ->with('sender')
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('type'), fn ($q, $type) => $q->where('document_type', $type))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // Resolve the referenced documents (number + client) without a real polymorph.
        $quotationIds = $deliveries->where('document_type', 'quotation')->pluck('document_id');
        $invoiceIds = $deliveries->where('document_type', 'proforma_invoice')->pluck('document_id');
        $quotations = Quotation::query()->with('client')->whereIn('id', $quotationIds)->get()->keyBy('id');
        $invoices = ProformaInvoice::query()->with('client')->whereIn('id', $invoiceIds)->get()->keyBy('id');

        $deliveries->getCollection()->transform(function (DocumentEmailDelivery $delivery) use ($quotations, $invoices) {
            $doc = $delivery->document_type === 'quotation'
                ? ($quotations[$delivery->document_id] ?? null)
                : ($invoices[$delivery->document_id] ?? null);
            $delivery->document_number = $doc?->number;
            $delivery->document_client = $doc?->client?->company_name
                ?? ($doc?->client_snapshot['company_name'] ?? null);
            $delivery->document_url = $doc
                ? ($delivery->document_type === 'quotation'
                    ? route('quotations.show', $doc)
                    : route('proforma-invoices.show', $doc))
                : null;

            return $delivery;
        });

        return view('email_deliveries.index', [
            'deliveries' => $deliveries,
            'sentCount' => DocumentEmailDelivery::query()->where('status', 'sent')->count(),
            'failedCount' => DocumentEmailDelivery::query()->where('status', 'failed')->count(),
        ]);
    }

    public function quotationCreate(Quotation $quotation, DocumentEmailService $emails): View
    {
        $quotation->load('client', 'items.service');

        return view('document_emails.form', [
            'document' => $quotation,
            'documentType' => 'quotation',
            'title' => 'Send Quotation Email',
            'sendRoute' => route('quotations.email.send', $quotation),
            'backRoute' => route('quotations.show', $quotation),
            'draft' => $emails->draft('quotation', $quotation),
        ]);
    }

    public function quotationSend(Request $request, Quotation $quotation, DocumentEmailService $emails): RedirectResponse
    {
        return $this->send($request, $emails, 'quotation', $quotation, route('quotations.email.create', $quotation), route('quotations.show', $quotation));
    }

    public function proformaCreate(ProformaInvoice $proformaInvoice, DocumentEmailService $emails): View
    {
        $proformaInvoice->load('client', 'items.service');

        return view('document_emails.form', [
            'document' => $proformaInvoice,
            'documentType' => 'proforma_invoice',
            'title' => 'Send Proforma Invoice Email',
            'sendRoute' => route('proforma-invoices.email.send', $proformaInvoice),
            'backRoute' => route('proforma-invoices.show', $proformaInvoice),
            'draft' => $emails->draft('proforma_invoice', $proformaInvoice),
        ]);
    }

    public function proformaSend(Request $request, ProformaInvoice $proformaInvoice, DocumentEmailService $emails): RedirectResponse
    {
        return $this->send($request, $emails, 'proforma_invoice', $proformaInvoice, route('proforma-invoices.email.create', $proformaInvoice), route('proforma-invoices.show', $proformaInvoice));
    }

    private function send(Request $request, DocumentEmailService $emails, string $documentType, $document, string $formRoute, string $showRoute): RedirectResponse
    {
        $data = $this->validated($request, $emails);

        try {
            $delivery = $emails->send($documentType, $document, $data, $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            return redirect($formRoute)
                ->withInput()
                ->withErrors(['email' => 'Email could not be sent. Please check the email configuration and try again.']);
        }

        $ccEmails = $delivery->cc_emails ?: [];
        $message = $ccEmails === []
            ? "Email sent successfully to {$delivery->to_email}."
            : "Email sent successfully to {$delivery->to_email} with CC to ".implode(', ', $ccEmails).'.';

        return redirect($showRoute)->with('status', $message);
    }

    private function validated(Request $request, DocumentEmailService $emails): array
    {
        $validator = Validator::make($request->all(), [
            'to' => ['required', 'email', 'max:255'],
            'cc' => ['nullable', 'string'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
        ]);

        $validator->after(function ($validator) use ($request, $emails) {
            foreach ($emails->invalidCcEmails($request->input('cc')) as $email) {
                $validator->errors()->add('cc', "The CC address {$email} is invalid.");
            }
        });

        return $validator->validate();
    }
}
