<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Service;
use App\Models\Setting;
use App\Services\DocumentContentService;
use App\Services\DocumentNumberService;
use App\Services\DocumentPdfService;
use App\Services\ProformaInvoiceVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProformaInvoiceController extends Controller
{
    public function index(): View
    {
        return view('proforma_invoices.index', [
            'invoices' => ProformaInvoice::query()
                ->with('client', 'creator')
                ->when(request('search'), function ($query, string $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('number', 'like', "%{$search}%")
                            ->orWhereHas('client', fn ($query) => $query->where('company_name', 'like', "%{$search}%"));
                    });
                })
                ->latest('date')
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request, DocumentNumberService $numbers): View
    {
        $settings = Setting::current();

        return view('proforma_invoices.create', $this->formData([
            'invoice' => new ProformaInvoice([
                'client_id' => $request->integer('client_id') ?: null,
                'number' => $numbers->invoice(),
                'date' => now(),
                'payment_terms' => $settings->default_payment_terms,
                'adjustment' => 0,
                'vat_treatment' => $settings->quotation_vat_treatment ?: 'exclusive',
                'vat_rate' => $settings->quotation_vat_rate,
                'show_vat_separately' => $settings->quotation_show_vat_separately ?? true,
            ]),
        ]));
    }

    public function store(
        Request $request,
        DocumentNumberService $numbers,
        DocumentContentService $content,
        ProformaInvoiceVerificationService $verification
    ): RedirectResponse {
        $invoice = DB::transaction(function () use ($request, $numbers, $content, $verification) {
            $data = $this->validated($request);
            $client = $this->resolveClient($data);
            $bank = $this->resolveBank($data);
            $this->validateBankForPdf($bank, $request->input('after_save') === 'pdf');
            $settings = Setting::current();
            $services = $this->selectedServices($data['items']);
            $data = $this->applyDefaults($data, $content->invoiceDefaults($client, $services, $settings));
            [$items, $subtotal] = $this->items($data['items'], 'invoice', $content);
            $adjustment = (float) ($data['adjustment'] ?? 0);
            $vat = $this->vat($subtotal + $adjustment, $data);

            $invoice = ProformaInvoice::query()->create([
                ...$this->documentData($data),
                'client_id' => $client->id,
                'bank_account_id' => $bank?->id,
                'created_by' => $request->user()->id,
                'number' => $numbers->invoice(),
                'client_snapshot' => $this->clientSnapshot($client),
                'bank_snapshot' => $this->bankSnapshot($bank),
                'settings_snapshot' => $this->settingsSnapshot($settings),
                'subtotal' => $subtotal,
                'adjustment' => $adjustment,
                'vat_amount' => $vat,
                'total' => $subtotal + $adjustment + $vat,
            ]);

            $invoice->items()->createMany($items);
            $verification->apply($invoice->load('items'));

            return $invoice;
        });

        if ($request->input('after_save') === 'pdf') {
            return redirect()->route('proforma-invoices.pdf', $invoice);
        }

        return redirect()->route('proforma-invoices.show', $invoice)->with('status', 'Invoice saved.');
    }

    public function show(ProformaInvoice $proformaInvoice): View
    {
        $proformaInvoice->load('client', 'bankAccount', 'items.service', 'creator', 'emailDeliveries.sender');

        return view('proforma_invoices.show', ['invoice' => $proformaInvoice]);
    }

    public function edit(ProformaInvoice $proformaInvoice): View
    {
        $proformaInvoice->load('items');

        return view('proforma_invoices.edit', $this->formData(['invoice' => $proformaInvoice]));
    }

    public function update(
        Request $request,
        ProformaInvoice $proformaInvoice,
        DocumentContentService $content,
        ProformaInvoiceVerificationService $verification
    ): RedirectResponse {
        DB::transaction(function () use ($request, $proformaInvoice, $content, $verification) {
            $data = $this->validated($request);
            $client = $this->resolveClient($data);
            $bank = $this->resolveBank($data);
            $this->validateBankForPdf($bank, false);
            $settings = Setting::current();
            $services = $this->selectedServices($data['items']);
            $data = $this->applyDefaults($data, $content->invoiceDefaults($client, $services, $settings));
            [$items, $subtotal] = $this->items($data['items'], 'invoice', $content);
            $adjustment = (float) ($data['adjustment'] ?? 0);
            $vat = $this->vat($subtotal + $adjustment, $data);

            $proformaInvoice->update([
                ...$this->documentData($data),
                'client_id' => $client->id,
                'bank_account_id' => $bank?->id,
                'client_snapshot' => $this->clientSnapshot($client),
                'bank_snapshot' => $this->bankSnapshot($bank),
                'settings_snapshot' => $this->settingsSnapshot($settings),
                'subtotal' => $subtotal,
                'adjustment' => $adjustment,
                'vat_amount' => $vat,
                'total' => $subtotal + $adjustment + $vat,
            ]);

            $proformaInvoice->items()->delete();
            $proformaInvoice->items()->createMany($items);
            $verification->apply($proformaInvoice->load('items'));
        });

        return redirect()->route('proforma-invoices.show', $proformaInvoice)->with('status', 'Invoice updated.');
    }

    public function destroy(ProformaInvoice $proformaInvoice): RedirectResponse
    {
        $proformaInvoice->delete();

        return redirect()->route('proforma-invoices.index')->with('status', 'Invoice deleted.');
    }

    public function duplicate(
        ProformaInvoice $proformaInvoice,
        DocumentNumberService $numbers,
        ProformaInvoiceVerificationService $verification
    ): RedirectResponse {
        $copy = DB::transaction(function () use ($proformaInvoice, $numbers, $verification) {
            $proformaInvoice->load('items');
            $copy = $proformaInvoice->replicate([
                'number',
                'verification_payload_version',
                'verification_id',
                'verification_signature',
            ]);
            $copy->number = $numbers->invoice();
            $copy->date = now();
            $copy->created_by = auth()->id();
            $copy->save();

            foreach ($proformaInvoice->items as $item) {
                $copy->items()->create($item->replicate(['proforma_invoice_id'])->toArray());
            }
            $verification->apply($copy->load('items'));

            return $copy;
        });

        return redirect()->route('proforma-invoices.edit', $copy)->with('status', 'Invoice duplicated with a new number.');
    }

    public function pdf(
        ProformaInvoice $proformaInvoice,
        DocumentPdfService $pdfs
    ) {
        try {
            $pdf = $pdfs->proformaInvoicePdf($proformaInvoice);
        } catch (ValidationException) {
            return redirect()->route('proforma-invoices.show', $proformaInvoice)
                ->withErrors(['bank_account_id' => 'Configure and select a valid bank account before downloading the PDF.']);
        }

        return $pdf->download($pdfs->proformaInvoiceFilename($proformaInvoice));
    }

    private function formData(array $extra): array
    {
        return [
            ...$extra,
            'clients' => Client::query()->orderBy('company_name')->get(),
            'services' => Service::query()
                ->with(['components' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(),
            'settings' => Setting::current(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'new_client.company_name' => ['required_without:client_id', 'nullable', 'string', 'max:255'],
            'new_client.parent_company' => ['nullable', 'string', 'max:255'],
            'new_client.contact_person' => ['nullable', 'string', 'max:255'],
            'new_client.designation' => ['nullable', 'string', 'max:255'],
            'new_client.department' => ['nullable', 'string', 'max:255'],
            'new_client.email' => ['nullable', 'email', 'max:255'],
            'new_client.phone' => ['nullable', 'string', 'max:255'],
            'new_client.website' => ['nullable', 'string', 'max:255'],
            'new_client.address' => ['required_without:client_id', 'nullable', 'string'],
            'new_client.city' => ['nullable', 'string', 'max:255'],
            'new_client.postal_code' => ['nullable', 'string', 'max:255'],
            'new_client.country' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'date' => ['required', 'date'],
            'charge_for' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string'],
            'validity_text' => ['nullable', 'string'],
            'adjustment' => ['nullable', 'numeric'],
            'vat_treatment' => ['nullable', 'in:exclusive,included,add,not_applicable'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'show_vat_separately' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'after_save' => ['nullable', 'in:view,pdf'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.pricing_mode' => ['nullable', 'in:separate,consolidated'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.scope_items' => ['nullable'],
            'items.*.unit' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_rate' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function resolveClient(array $data): Client
    {
        if (! empty($data['client_id'])) {
            return Client::query()->findOrFail($data['client_id']);
        }

        return Client::query()->create($data['new_client']);
    }

    private function resolveBank(array $data): ?BankAccount
    {
        if (! empty($data['bank_account_id'])) {
            return BankAccount::query()->find($data['bank_account_id']);
        }

        $activeBanks = BankAccount::query()->where('is_active', true)->get();

        if ($default = $activeBanks->firstWhere('is_default', true)) {
            return $default;
        }

        return $activeBanks->count() === 1 ? $activeBanks->first() : null;
    }

    private function documentData(array $data): array
    {
        return collect($data)->except(['items', 'new_client', 'client_id', 'after_save'])->all();
    }

    private function vat(float $net, array $data): float
    {
        if (($data['vat_treatment'] ?? 'exclusive') !== 'add') {
            return 0.0;
        }

        $rate = (float) ($data['vat_rate'] ?? 0);

        return round($net * $rate / 100, 2);
    }

    private function clientSnapshot(?Client $client): ?array
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

    private function settingsSnapshot(Setting $settings): array
    {
        return $settings->only([
            'organization_name', 'logo_path', 'tagline', 'office_address', 'phone', 'email', 'website',
            'default_currency', 'currency_major_name', 'currency_minor_name',
            'prepared_by_name', 'prepared_by_designation', 'footer_text', 'pdf_note',
            'quotation_vat_treatment', 'quotation_vat_rate', 'quotation_show_vat_separately',
        ]);
    }

    private function selectedServices(array $input)
    {
        return Service::query()
            ->with('components')
            ->whereIn('id', collect($input)->pluck('service_id')->filter()->unique())
            ->get()
            ->keyBy('id')
            ->only(collect($input)->pluck('service_id')->filter()->all())
            ->values();
    }

    private function applyDefaults(array $data, array $defaults): array
    {
        foreach ($defaults as $key => $value) {
            if (($data[$key] ?? null) === null || trim((string) $data[$key]) === '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    private function items(array $input, string $type, DocumentContentService $content): array
    {
        $subtotal = 0;
        $items = [];

        foreach (array_values($input) as $index => $item) {
            $service = empty($item['service_id']) ? null : Service::query()->find($item['service_id']);
            $amount = (float) $item['quantity'] * (float) $item['unit_rate'];
            $subtotal += $amount;
            $items[] = [
                'service_id' => $item['service_id'] ?? null,
                'pricing_mode' => ($item['pricing_mode'] ?? null) ?: ($service?->defaultPricingMode() ?? 'separate'),
                'description' => $item['description'] ?: $content->serviceDescription($service, $type) ?: 'Service',
                'scope_items' => $this->scopeItems($item, $service),
                'unit' => $item['unit'] ?: ($service?->default_unit),
                'quantity' => $item['quantity'],
                'unit_rate' => $item['unit_rate'],
                'amount' => $amount,
                'sort_order' => $index + 1,
            ];
        }

        return [$items, $subtotal];
    }

    private function scopeItems(array $item, ?Service $service): array
    {
        $input = $item['scope_items'] ?? null;

        if (is_string($input)) {
            $items = preg_split('/\r\n|\r|\n/', $input);
        } elseif (is_array($input)) {
            $items = $input;
        } else {
            $items = [];
        }

        $items = collect($items)
            ->map(function ($scopeItem) {
                if (is_array($scopeItem)) {
                    return trim((string) ($scopeItem['name'] ?? ''));
                }

                return trim((string) $scopeItem);
            })
            ->filter()
            ->values()
            ->all();

        if ($items !== []) {
            return $items;
        }

        return $service?->activeComponents
            ? $service->activeComponents->pluck('name')->filter()->values()->all()
            : [];
    }

    private function validateBankForPdf(?BankAccount $bank, bool $isPdf): void
    {
        if (! $isPdf || $this->isValidBankSnapshot($this->bankSnapshot($bank))) {
            return;
        }

        $message = BankAccount::query()->where('is_active', true)->exists()
            ? 'Select a valid active bank account before downloading the PDF.'
            : 'Configure an active bank account before downloading the PDF.';

        throw ValidationException::withMessages(['bank_account_id' => $message]);
    }

    private function isValidBankSnapshot(?array $bank): bool
    {
        return filled($bank['beneficiary_name'] ?? null)
            && filled($bank['bank_name'] ?? null)
            && filled($bank['account_number'] ?? null);
    }
}
