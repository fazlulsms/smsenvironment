<?php

namespace App\Providers;

use App\Models\AssessmentSchedule;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\ServiceInquiry;
use App\Models\User;
use App\Support\CurrentEntity;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentEntity::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        $this->defineAuthorization();

        // Sidebar counts — small, cached-per-request COUNT queries for the app shell.
        View::composer('layouts.partials.sidebar', function ($view) {
            $view->with('sidebarCounts', [
                'quotations' => Quotation::query()->count(),
                'invoices' => ProformaInvoice::query()->count(),
                'clients' => Client::query()->count(),
                'inquiries_new' => ServiceInquiry::query()->where('status', 'new')->count(),
                'reassess_due' => AssessmentSchedule::query()
                    ->where('status', 'completed')->whereNotNull('next_reassessment_date')
                    ->whereDate('next_reassessment_date', '<=', now()->addDays(30))->count(),
            ]);
        });
    }

    /**
     * Central authorization map. Super Admin bypasses every check via Gate::before;
     * the ability gates below only need to describe Admin vs Staff. Model-level
     * rules (per-record delete safety) live in the Policy classes.
     */
    private function defineAuthorization(): void
    {
        // Super Admin has full control everywhere.
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        // Admin + Super Admin — normal business administration.
        Gate::define('manage-banks', fn (User $user) => $user->hasAdminAccess());
        Gate::define('manage-services', fn (User $user) => $user->hasAdminAccess());
        Gate::define('manage-standards', fn (User $user) => $user->hasAdminAccess());
        Gate::define('view-email-deliveries', fn (User $user) => $user->hasAdminAccess());
        // Operational management (assessors master, financial corrections, cancels).
        Gate::define('manage-assessors', fn (User $user) => $user->hasAdminAccess());
        Gate::define('delete-payments', fn (User $user) => $user->hasAdminAccess());
        Gate::define('cancel-schedules', fn (User $user) => $user->hasAdminAccess());

        // Super Admin only — system, security and financial configuration.
        // (Super Admin already passes via Gate::before; these deny everyone else.)
        Gate::define('manage-users', fn (User $user) => false);
        Gate::define('manage-settings', fn (User $user) => false);
        Gate::define('manage-entities', fn (User $user) => false);
        Gate::define('manage-email-accounts', fn (User $user) => false);
        Gate::define('delete-email-deliveries', fn (User $user) => false);
    }
}
