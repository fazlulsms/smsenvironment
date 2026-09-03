<?php

namespace App\Http\Controllers;

use App\Models\InvoicePayment;
use App\Models\ProformaInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class InvoicePaymentController extends Controller
{
    public function store(Request $request, ProformaInvoice $proformaInvoice): RedirectResponse
    {
        $due = $proformaInvoice->dueAmount();

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max(0.01, $due)],
            'received_date' => ['required', 'date'],
            'method' => ['nullable', Rule::in(InvoicePayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'amount.max' => 'Amount cannot exceed the outstanding due of '.number_format($due, 2).'.',
        ]);

        $proformaInvoice->payments()->create([
            'business_entity_id' => $proformaInvoice->business_entity_id,
            'amount' => $data['amount'],
            'currency' => $proformaInvoice->payableCurrency(),
            'received_date' => $data['received_date'],
            'method' => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Payment recorded.');
    }

    public function destroy(ProformaInvoice $proformaInvoice, InvoicePayment $payment): RedirectResponse
    {
        Gate::authorize('delete-payments');
        abort_unless($payment->proforma_invoice_id === $proformaInvoice->id, 404);

        Log::warning('Invoice payment deleted', [
            'invoice' => $proformaInvoice->number,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
            'by_user_id' => auth()->id(),
        ]);

        $payment->delete();

        return back()->with('status', 'Payment removed.');
    }
}
