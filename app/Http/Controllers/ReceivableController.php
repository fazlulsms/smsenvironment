<?php

namespace App\Http\Controllers;

use App\Models\ProformaInvoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivableController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'all');

        $invoices = ProformaInvoice::query()
            ->with(['client', 'items.service'])
            ->withSum('payments as received_sum', 'amount')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->to))
            ->when($request->search, fn ($q, $s) => $q->where('number', 'like', "%{$s}%"))
            ->when(in_array($filter, ['won', 'lost', 'sent', 'draft']), fn ($q) => $q->where('commercial_status', $filter))
            ->when($filter === 'unpaid', fn ($q) => $q->whereDoesntHave('payments'))
            ->when($filter === 'paid', fn ($q) => $q->whereRaw('(select coalesce(sum(amount),0) from invoice_payments where invoice_payments.proforma_invoice_id = proforma_invoices.id) >= total'))
            ->when($filter === 'partial', fn ($q) => $q->whereHas('payments')
                ->whereRaw('(select coalesce(sum(amount),0) from invoice_payments where invoice_payments.proforma_invoice_id = proforma_invoices.id) < total'))
            ->latest('date')
            ->paginate(25)
            ->withQueryString();

        return view('receivables.index', [
            'invoices' => $invoices,
            'filter' => $filter,
        ]);
    }
}
