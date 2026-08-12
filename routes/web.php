<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BusinessEntityController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentEmailController;
use App\Http\Controllers\ProformaInvoiceController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TestEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('entities/overview', [BusinessEntityController::class, 'overview'])->name('entities.overview');
    Route::post('entities/switch', [BusinessEntityController::class, 'switch'])->name('entities.switch');

    Route::post('clients/smart-paste', [ClientController::class, 'smartPaste'])->name('clients.smart-paste');
    Route::post('clients/smart-store', [ClientController::class, 'smartStore'])->name('clients.smart-store');
    Route::resource('clients', ClientController::class);
    Route::resource('services', ServiceController::class);
    Route::post('bank-accounts/{bankAccount}/default', [BankAccountController::class, 'setDefault'])->name('bank-accounts.default');
    Route::resource('bank-accounts', BankAccountController::class)->parameters(['bank-accounts' => 'bankAccount']);
    Route::get('email-deliveries', [DocumentEmailController::class, 'index'])->name('email-deliveries.index');

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
