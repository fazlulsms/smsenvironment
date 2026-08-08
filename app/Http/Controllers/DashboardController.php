<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\Service;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'clientsCount' => Client::query()->count(),
            'servicesCount' => Service::query()->count(),
            'recentQuotations' => Quotation::query()->with('client', 'creator')->latest()->limit(5)->get(),
            'recentInvoices' => ProformaInvoice::query()->with('client', 'creator')->latest()->limit(5)->get(),
        ]);
    }
}
