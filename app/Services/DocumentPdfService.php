<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\ProformaInvoice;
use App\Models\Quotation;
use App\Models\Setting;
use App\Support\DocumentProfile;
use App\Support\InvoiceMoney;
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

        $profile = DocumentProfile::forInvoice($invoice);
        $money = InvoiceMoney::context($invoice, $settings);
        $entity = BusinessEntity::query()->where('entity_code', $invoice->entity_code)->first();
        // Branding logo: prefer the immutable snapshot, but if it has none or the
        // file is gone (e.g. an older invoice, or a logo replaced via Settings),
        // fall back to the entity's current logo so the document is never unbranded.
        $settings['logo_path'] = $this->resolveLogoPath($settings['logo_path'] ?? null, $entity);

        return PdfFacade::loadView($profile['pdf_view'], [
            'invoice' => $invoice,
            'settings' => $settings,
            'entity' => $entity,
            'profile' => $profile,
            'money' => $money,
            'client' => $invoice->client_snapshot ?: $this->clientSnapshot($invoice->client),
            'bank' => $bank,
            // Verification/QR is a per-profile decision — profiles that opt out
            // (e.g. Eidikos) never render or even generate a QR.
            'verificationQr' => $profile['show_verification'] ? $this->invoiceVerification->qrDataUri($invoice) : null,
            'amountInWords' => $this->words->convert(
                $money['words_amount'],
                $money['words_currency'],
                $settings['currency_major_name'] ?? 'Taka',
                $settings['currency_minor_name'] ?? 'Paisa'
            ),
            // Explicit BDT-equivalent-in-words, only when a foreign currency is converted.
            'bdtEquivalentInWords' => $money['dual']
                ? $this->words->convert($money['base_words_amount'], InvoiceMoney::BASE, 'Taka', 'Paisa')
                : null,
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

    /**
     * Resolve a usable logo path. Keeps the snapshot logo when its file still
     * exists (historical fidelity); otherwise falls back to the entity's current
     * settings logo, then its entity logo, so branding never silently disappears.
     */
    private function resolveLogoPath(?string $snapshotLogo, ?BusinessEntity $entity): ?string
    {
        $exists = fn (?string $path) => filled($path) && file_exists(storage_path('app/public/'.$path));

        if ($exists($snapshotLogo)) {
            return $snapshotLogo;
        }

        $liveSettingsLogo = $entity
            ? Setting::query()->where('business_entity_id', $entity->id)->value('logo_path')
            : null;

        if ($exists($liveSettingsLogo)) {
            return $liveSettingsLogo;
        }

        return $exists($entity?->logo_path) ? $entity->logo_path : $snapshotLogo;
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
