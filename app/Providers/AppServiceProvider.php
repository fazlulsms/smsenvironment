<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Sidebar counts — small, cached-per-request COUNT queries for the app shell.
        View::composer('layouts.partials.sidebar', function ($view) {
            $view->with('sidebarCounts', [
                'quotations' => Quotation::query()->count(),
                'invoices' => ProformaInvoice::query()->count(),
                'clients' => Client::query()->count(),
            ]);
        });
    }
}
