<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $preset = $request->input('period', 'month');
        $dash = DashboardService::forPeriod($preset, $request->input('from'), $request->input('to'));

        return view('dashboard', [
            'preset' => $preset,
            'period' => $dash->period(),
            'invoiceKpis' => $dash->invoiceKpis(),
            'receivableKpis' => $dash->receivableKpis(),
            'scheduleKpis' => $dash->scheduleKpis(),
            'monthlyInvoiced' => $dash->monthlyInvoiced(),
            'serviceReport' => $dash->serviceReport(),
            'assessorReport' => $dash->assessorReport(),

            'clientsCount' => Client::query()->count(),
            'recentQuotations' => Quotation::query()->with('client', 'items.service', 'emailDeliveries')->latest()->limit(5)->get(),
            'recentInvoices' => ProformaInvoice::query()->with('client', 'items.service', 'emailDeliveries')->latest()->limit(5)->get(),
        ]);
    }
}
