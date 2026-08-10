<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\DocumentEmailDelivery;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $monthStart = Carbon::now()->startOfMonth();

        return view('dashboard', [
            'clientsCount' => Client::query()->count(),
            'servicesCount' => Service::query()->where('is_active', true)->count(),
            'quotationsCount' => Quotation::query()->count(),
            'invoicesCount' => ProformaInvoice::query()->count(),
            'quotedValue' => (float) Quotation::query()->sum('total'),
            'invoicedValue' => (float) ProformaInvoice::query()->sum('total'),
            'emailsSent' => DocumentEmailDelivery::query()->where('status', 'sent')->count(),

            'quotedThisMonth' => (float) Quotation::query()->where('date', '>=', $monthStart)->sum('total'),
            'invoicedThisMonth' => (float) ProformaInvoice::query()->where('date', '>=', $monthStart)->sum('total'),
            'docsThisMonth' => Quotation::query()->where('date', '>=', $monthStart)->count()
                + ProformaInvoice::query()->where('date', '>=', $monthStart)->count(),
            'emailsThisMonth' => DocumentEmailDelivery::query()->where('status', 'sent')
                ->where('created_at', '>=', $monthStart)->count(),

            'recentQuotations' => Quotation::query()->with('client', 'items.service', 'emailDeliveries')->latest()->limit(6)->get(),
            'recentInvoices' => ProformaInvoice::query()->with('client', 'items.service', 'emailDeliveries')->latest()->limit(6)->get(),
            'recentClients' => Client::query()->latest()->limit(5)->get(),
        ]);
    }
}
