<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BusinessEntityController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommercialDraftController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentEmailController;
use App\Http\Controllers\EmailAccountController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ProformaInvoiceController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\QuickEnvironmentalController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StandardController;
use App\Http\Controllers\TestEmailController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Public SMS Environmental Alliance website (domain root, no authentication).
// ---------------------------------------------------------------------------
Route::get('/', [PublicSiteController::class, 'home'])->name('public.home');
Route::get('/services', [PublicSiteController::class, 'services'])->name('public.services');
Route::get('/training', [PublicSiteController::class, 'training'])->name('public.training');
Route::get('/about', [PublicSiteController::class, 'about'])->name('public.about');
Route::get('/contact', [PublicSiteController::class, 'contact'])->name('public.contact');
Route::get('/privacy', [PublicSiteController::class, 'privacy'])->name('public.privacy');
Route::get('/terms', [PublicSiteController::class, 'terms'])->name('public.terms');
Route::post('/request-proposal', [PublicSiteController::class, 'storeInquiry'])
    ->middleware('throttle:6,1')->name('public.inquiry');
Route::get('/sitemap.xml', [PublicSiteController::class, 'sitemap'])->name('public.sitemap');
Route::get('/robots.txt', [PublicSiteController::class, 'robots'])->name('public.robots');

// Public document verification portal (QR target + lookup by number).
Route::get('verify', [VerificationController::class, 'index'])->name('verify.index');
Route::get('verify/{code}', [VerificationController::class, 'show'])->name('verify.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// The authenticated SMSEA Office application lives entirely under /office.
// Route names are unchanged, so all existing route() references keep working.
Route::middleware('auth')->prefix('office')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('entities/overview', [BusinessEntityController::class, 'overview'])->name('entities.overview');
    Route::post('entities/switch', [BusinessEntityController::class, 'switch'])->name('entities.switch');
    Route::get('entities', [BusinessEntityController::class, 'index'])->name('entities.index');
    Route::get('entities/{entity}/edit', [BusinessEntityController::class, 'edit'])->name('entities.edit');
    Route::put('entities/{entity}', [BusinessEntityController::class, 'update'])->name('entities.update');

    Route::get('quick-environmental', [QuickEnvironmentalController::class, 'index'])->name('quick-env.index');
    Route::post('quick-environmental/prepare', [QuickEnvironmentalController::class, 'prepare'])->name('quick-env.prepare');

    Route::get('ai-draft', [CommercialDraftController::class, 'index'])->name('ai-draft.index');
    Route::post('ai-draft/analyze', [CommercialDraftController::class, 'analyze'])->name('ai-draft.analyze');
    Route::post('ai-draft/apply', [CommercialDraftController::class, 'apply'])->name('ai-draft.apply');

    // Website inquiries → review → bridge into the normal client/quotation flows.
    Route::get('inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
    Route::get('inquiries/{inquiry}', [InquiryController::class, 'show'])->name('inquiries.show');
    Route::patch('inquiries/{inquiry}/status', [InquiryController::class, 'updateStatus'])->name('inquiries.status');
    Route::post('inquiries/{inquiry}/client', [InquiryController::class, 'createClient'])->name('inquiries.client');
    Route::post('inquiries/{inquiry}/quotation', [InquiryController::class, 'prepareQuotation'])->name('inquiries.quotation');

    Route::post('clients/smart-paste', [ClientController::class, 'smartPaste'])->name('clients.smart-paste');
    Route::post('clients/smart-store', [ClientController::class, 'smartStore'])->name('clients.smart-store');
    Route::resource('clients', ClientController::class);
    Route::resource('services', ServiceController::class);
    Route::get('catalogue/standards/create', [StandardController::class, 'create'])->name('catalogue-standards.create');
    Route::post('catalogue/standards', [StandardController::class, 'store'])->name('catalogue-standards.store');
    Route::get('catalogue/standards/{standard}/edit', [StandardController::class, 'edit'])->name('catalogue-standards.edit');
    Route::put('catalogue/standards/{standard}', [StandardController::class, 'update'])->name('catalogue-standards.update');
    Route::post('bank-accounts/{bankAccount}/default', [BankAccountController::class, 'setDefault'])->name('bank-accounts.default');
    Route::resource('bank-accounts', BankAccountController::class)->parameters(['bank-accounts' => 'bankAccount']);
    Route::get('email-deliveries', [DocumentEmailController::class, 'index'])->name('email-deliveries.index');
    Route::resource('email-accounts', EmailAccountController::class)->parameters(['email-accounts' => 'emailAccount'])->except(['show']);

    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('settings/test-email', TestEmailController::class)->name('settings.test-email');

    Route::post('quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');
    Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::get('quotations/{quotation}/email', [DocumentEmailController::class, 'quotationCreate'])->name('quotations.email.create');
    Route::post('quotations/{quotation}/email', [DocumentEmailController::class, 'quotationSend'])->name('quotations.email.send');
    Route::resource('quotations', QuotationController::class);

    Route::post('proforma-invoices/{proformaInvoice}/duplicate', [ProformaInvoiceController::class, 'duplicate'])->name('proforma-invoices.duplicate');
    Route::get('proforma-invoices/{proformaInvoice}/pdf', [ProformaInvoiceController::class, 'pdf'])->name('proforma-invoices.pdf');
    Route::get('proforma-invoices/{proformaInvoice}/email', [DocumentEmailController::class, 'proformaCreate'])->name('proforma-invoices.email.create');
    Route::post('proforma-invoices/{proformaInvoice}/email', [DocumentEmailController::class, 'proformaSend'])->name('proforma-invoices.email.send');
    Route::resource('proforma-invoices', ProformaInvoiceController::class)->parameters(['proforma-invoices' => 'proformaInvoice']);
});
