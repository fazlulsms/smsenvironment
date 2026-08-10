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
        $bank = $this->resolveBankSnapshot(
            $quotation->bank_snapshot ?: $this->bankSnapshot($quotation->bankAccount),
            $quotation->bankAccount
        );

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
        $bank = $this->resolveBankSnapshot(
            $invoice->bank_snapshot ?: $this->bankSnapshot($invoice->bankAccount),
            $invoice->bankAccount
        );

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

    /**
     * Return a usable bank snapshot, substituting a real configured bank when the
     * stored snapshot is a leftover development/test bank. Shared by quotations and
     * proforma invoices so no professional document ever renders a placeholder bank.
     */
    public function resolveBankSnapshot(?array $snapshot, ?BankAccount $selected): ?array
    {
        if (! $this->isDevelopmentBankSnapshot($snapshot)) {
            return $snapshot;
        }

        if ($selected && ! $this->isDevelopmentBankSnapshot($this->bankSnapshot($selected))) {
            return $this->bankSnapshot($selected);
        }

        $fallback = BankAccount::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->first(fn (BankAccount $bank) => ! $this->isDevelopmentBankSnapshot($this->bankSnapshot($bank)));

        return $this->bankSnapshot($fallback) ?: $snapshot;
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
