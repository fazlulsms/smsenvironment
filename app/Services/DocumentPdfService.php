<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF;
use Illuminate\Validation\ValidationException;

class DocumentPdfService
{
    public function __construct(
        private AmountInWords $words,
        private QuotationVerificationService $quotationVerification,
        private ProformaInvoiceVerificationService $invoiceVerification,
        private DocumentFilenameService $filenames
    ) {}

    public function quotationPdf(Quotation $quotation): PDF
    {
        $quotation->loadMissing('client', 'bankAccount', 'items.service', 'creator');
        $settings = $quotation->settings_snapshot ?: Setting::current()->toArray();
        $bank = $quotation->bank_snapshot ?: $this->bankSnapshot($quotation->bankAccount);

        $this->ensureBankSnapshot($bank);

        return PdfFacade::loadView('quotations.pdf', [
            'quotation' => $quotation,
            'settings' => $settings,
            'client' => $quotation->client_snapshot ?: $this->clientSnapshot($quotation->client),
            'bank' => $bank,
            'verificationQr' => $this->quotationVerification->qrDataUri($quotation),
            'amountInWords' => $this->words->convert(
                $quotation->total,
                $settings['default_currency'] ?? 'BDT',
                $settings['currency_major_name'] ?? 'Taka',
                $settings['currency_minor_name'] ?? 'Paisa'
            ),
        ])->setPaper('a4');
    }

    public function proformaInvoicePdf(ProformaInvoice $invoice): PDF
    {
        $invoice->loadMissing('client', 'bankAccount', 'items.service', 'creator');
        $settings = $invoice->settings_snapshot ?: Setting::current()->toArray();
        $bank = $this->realBankSnapshot($invoice->bank_snapshot ?: $this->bankSnapshot($invoice->bankAccount), $invoice);

        $this->ensureBankSnapshot($bank);

        return PdfFacade::loadView('proforma_invoices.pdf', [
            'invoice' => $invoice,
            'settings' => $settings,
            'client' => $invoice->client_snapshot ?: $this->clientSnapshot($invoice->client),
            'bank' => $bank,
            'verificationQr' => $this->invoiceVerification->qrDataUri($invoice),
            'amountInWords' => $this->words->convert(
                $invoice->total,
                $settings['default_currency'] ?? 'BDT',
                $settings['currency_major_name'] ?? 'Taka',
                $settings['currency_minor_name'] ?? 'Paisa'
            ),
        ])->setPaper('a4');
    }

    public function quotationFilename(Quotation $quotation): string
    {
        return $this->filenames->quotationFilename($quotation);
    }

    public function proformaInvoiceFilename(ProformaInvoice $invoice): string
    {
        return $this->filenames->proformaInvoiceFilename($invoice);
    }

    private function ensureBankSnapshot(?array $bank): void
    {
        if ($this->isValidBankSnapshot($bank)) {
            return;
        }

        throw ValidationException::withMessages([
            'bank_account_id' => 'Configure and select a valid bank account before preparing the PDF.',
        ]);
    }

    private function clientSnapshot($client): ?array
    {
        return $client?->only([
            'company_name', 'parent_company', 'contact_person', 'designation', 'department',
            'email', 'phone', 'website', 'address', 'city', 'postal_code', 'country',
        ]);
    }

    private function bankSnapshot(?BankAccount $bank): ?array
    {
        return $bank?->only([
            'beneficiary_name', 'bank_name', 'branch', 'account_number', 'routing_number', 'swift_code',
        ]);
    }

    private function isValidBankSnapshot(?array $bank): bool
    {
        return filled($bank['beneficiary_name'] ?? null)
            && filled($bank['bank_name'] ?? null)
            && filled($bank['account_number'] ?? null);
    }

    private function realBankSnapshot(?array $bank, ProformaInvoice $invoice): ?array
    {
        if (! $this->isDevelopmentBankSnapshot($bank)) {
            return $bank;
        }

        $selectedBank = $invoice->bankAccount;
        if ($selectedBank && ! $this->isDevelopmentBankSnapshot($this->bankSnapshot($selectedBank))) {
            return $this->bankSnapshot($selectedBank);
        }

        $configuredBank = BankAccount::query()
            ->whereIn('account_number', ['2170316017001', '1301000014453'])
            ->orderByDesc('is_default')
            ->orderBy('bank_name')
            ->first();

        return $this->bankSnapshot($configuredBank) ?: $bank;
    }

    private function isDevelopmentBankSnapshot(?array $bank): bool
    {
        $bankName = strtolower((string) ($bank['bank_name'] ?? ''));
        $accountNumber = preg_replace('/\D+/', '', (string) ($bank['account_number'] ?? ''));

        return str_contains($bankName, 'local verification')
            || str_contains($bankName, 'test bank')
            || $accountNumber === '1234567890'
            || $accountNumber === '123456789';
    }
}
