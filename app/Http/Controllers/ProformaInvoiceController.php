<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDocumentStandards;
use App\Models\BankAccount;
use App\Models\ChargeParticular;
use App\Models\Client;
use App\Models\ProformaInvoice;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Services\DocumentContentService;
use App\Services\DocumentNumberService;
use App\Services\DocumentPdfService;
use App\Services\ProformaInvoiceVerificationService;
use App\Support\CurrentEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProformaInvoiceController extends Controller
{
    use HandlesDocumentStandards;

    public function index(): View
    {
        return view('proforma_invoices.index', [
            'invoices' => ProformaInvoice::query()
                ->with('client', 'creator', 'items.service', 'emailDeliveries')
                ->when(request('search'), function ($query, string $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('number', 'like', "%{$search}%")
                            ->orWhereHas('client', fn ($query) => $query->where('company_name', 'like', "%{$search}%"));
                    });
                })
                ->when(request('email') === 'sent', fn ($q) => $q->whereHas('emailDeliveries', fn ($d) => $d->where('status', 'sent')))
                ->when(request('email') === 'not_sent', fn ($q) => $q->whereDoesntHave('emailDeliveries'))
                ->when(request('email') === 'failed', fn ($q) => $q->whereHas('emailDeliveries')->whereDoesntHave('emailDeliveries', fn ($d) => $d->where('status', 'sent')))
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
            $standards = $this->selectedStandards($data);
            $category = $this->standardsCategory($data);
            $data = $this->applyStandardsToData($data, $category, $standards);
            $data['items'] = $this->normalizeItems($data);
            $data['charge_for'] = $this->resolveChargeFor($data);
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
            $this->syncDocumentStandards('proforma_invoice', $invoice, $category, $standards);
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
        $proformaInvoice->load('client', 'bankAccount', 'items.service', 'creator', 'emailDeliveries.sender', 'payments.recorder');

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
            $standards = $this->selectedStandards($data);
            $category = $this->standardsCategory($data);
            $data = $this->applyStandardsToData($data, $category, $standards);
            $data['items'] = $this->normalizeItems($data);
            $data['charge_for'] = $this->resolveChargeFor($data);
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
            $this->syncDocumentStandards('proforma_invoice', $proformaInvoice, $category, $standards);
            $verification->apply($proformaInvoice->load('items'));
        });

        return redirect()->route('proforma-invoices.show', $proformaInvoice)->with('status', 'Invoice updated.');
    }

    public function updateStatus(Request $request, ProformaInvoice $proformaInvoice): RedirectResponse
    {
        $data = $request->validate([
            'commercial_status' => ['required', Rule::in(array_keys(ProformaInvoice::COMMERCIAL_STATUSES))],
            'lost_reason' => ['nullable', Rule::in(ProformaInvoice::LOST_REASONS)],
            'lost_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $proformaInvoice->commercial_status = $data['commercial_status'];
        $proformaInvoice->status_updated_at = now();
        if ($data['commercial_status'] === ProformaInvoice::STATUS_LOST) {
            $proformaInvoice->lost_reason = $data['lost_reason'] ?? null;
            $proformaInvoice->lost_note = $data['lost_note'] ?? null;
        } else {
            $proformaInvoice->lost_reason = null;
            $proformaInvoice->lost_note = null;
        }
        $proformaInvoice->save();

        return back()->with('status', 'Invoice marked as '.ProformaInvoice::COMMERCIAL_STATUSES[$data['commercial_status']].'.');
    }

    /** Mark as Sent for offline sending (WhatsApp, external email, printed PDF). */
    public function markSent(ProformaInvoice $proformaInvoice): RedirectResponse
    {
        if ($proformaInvoice->commercial_status === ProformaInvoice::STATUS_DRAFT) {
            $proformaInvoice->update(['commercial_status' => ProformaInvoice::STATUS_SENT, 'status_updated_at' => now()]);
        }

        return back()->with('status', 'Invoice marked as sent.');
    }

    public function destroy(ProformaInvoice $proformaInvoice): RedirectResponse
    {
        $this->authorize('delete', $proformaInvoice);

        $wasIssued = $proformaInvoice->wasEmailed();

        // Soft delete only — the row (and its number) is preserved, so numbering
        // stays monotonic and any QR verification of an issued document still works.
        $proformaInvoice->delete();

        Log::warning('Proforma invoice deleted', [
            'invoice_id' => $proformaInvoice->id,
            'number' => $proformaInvoice->number,
            'was_issued' => $wasIssued,
            'by_user_id' => auth()->id(),
        ]);

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
            [$category, $standards] = $this->standardsFromSnapshot($copy->standards_snapshot, $copy->service_category_id);
            $this->syncDocumentStandards('proforma_invoice', $copy, $category, $standards);
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
                ->availableForEntity(app(CurrentEntity::class)->id())
                ->with(['components' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(),
            'settings' => Setting::current(),
            'serviceCategories' => ServiceCategory::query()->active()->ordered()
                ->with(['activeStandards'])->get(),
            'chargeParticulars' => ChargeParticular::query()->active()->ordered()
                ->get(['id', 'name', 'category', 'search_keywords']),
        ];
    }

    private function validated(Request $request): array
    {
        $mode = $request->input('charge_presentation', ProformaInvoice::PRESENTATION_ITEMIZED);
        // Selected standards auto-generate the title, so it is only required when the
        // single-charge modes are used without a standards selection.
        $titleRequired = in_array($mode, [ProformaInvoice::PRESENTATION_CONSOLIDATED, ProformaInvoice::PRESENTATION_BREAKDOWN], true)
            && ! filled($request->input('standards'));

        $rules = [
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
            'reference_no' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:8'],
            'conversion_rate' => ['nullable', 'numeric', 'min:0'],
            'service_category_id' => ['nullable', 'exists:service_categories,id'],
            'standards' => ['nullable', 'array'],
            'standards.*' => ['integer', 'exists:standards,id'],
            'charge_presentation' => ['nullable', 'in:consolidated,component_breakdown,itemized'],
            'charge_title' => [$titleRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'charge_for' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string'],
            'validity_text' => ['nullable', 'string'],
            'adjustment' => ['nullable', 'numeric'],
            'vat_treatment' => ['nullable', 'in:exclusive,included,add,not_applicable'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'show_vat_separately' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'after_save' => ['nullable', 'in:view,pdf'],
        ];

        $rules += match ($mode) {
            ProformaInvoice::PRESENTATION_CONSOLIDATED => [
                'consolidated.service_id' => ['nullable', 'exists:services,id'],
                // Nullable: selected standards can supply the description instead.
                'consolidated.description' => ['nullable', 'string'],
                'consolidated.amount' => ['nullable', 'numeric', 'min:0'],
                'consolidated.unit_rate' => ['nullable', 'numeric', 'min:0'], // legacy alias
            ],
            ProformaInvoice::PRESENTATION_BREAKDOWN => [
                'breakdown.service_id' => ['nullable', 'exists:services,id'],
                // Nullable: selected standards can supply the component list instead.
                'breakdown.components' => ['nullable', 'string'],
                'breakdown.unit' => ['nullable', 'string', 'max:255'],
                'breakdown.amount' => ['required', 'numeric', 'min:0'],
            ],
            default => [
                'items' => ['required', 'array', 'min:1'],
                'items.*.service_id' => ['nullable', 'exists:services,id'],
                'items.*.description' => ['nullable', 'string'],
                'items.*.scope_items' => ['nullable'],
                'items.*.amount' => ['nullable', 'numeric', 'min:0'],
                // Legacy fields kept optional for backward compatibility.
                'items.*.unit' => ['nullable', 'string', 'max:255'],
                'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
                'items.*.unit_rate' => ['nullable', 'numeric', 'min:0'],
            ],
        };

        $validator = Validator::make($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $mode) {
            $hasStandards = filled($request->input('standards'));

            if ($mode === ProformaInvoice::PRESENTATION_BREAKDOWN
                && ! $hasStandards
                && $this->splitLines($request->input('breakdown.components')) === []) {
                $validator->errors()->add('breakdown.components', 'Add at least one component or select standards.');
            }

            if ($mode === ProformaInvoice::PRESENTATION_CONSOLIDATED
                && ! $hasStandards
                && trim((string) $request->input('consolidated.description')) === '') {
                $validator->errors()->add('consolidated.description', 'Add a description or select standards.');
            }
        });

        return $validator->validate();
    }

    /**
     * Normalise the mode-specific inputs into the shared item row structure the
     * rest of the pipeline (pricing, snapshot, PDF, QR) already understands.
     */
    private function normalizeItems(array $data): array
    {
        $mode = $data['charge_presentation'] ?? ProformaInvoice::PRESENTATION_ITEMIZED;

        if ($mode === ProformaInvoice::PRESENTATION_CONSOLIDATED) {
            $c = $data['consolidated'] ?? [];

            return [[
                'service_id' => $c['service_id'] ?? null,
                'pricing_mode' => 'consolidated',
                'description' => $c['description'] ?? '',
                'scope_items' => '',
                'quantity' => 1,
                'amount' => $c['amount'] ?? $c['unit_rate'] ?? 0,
            ]];
        }

        if ($mode === ProformaInvoice::PRESENTATION_BREAKDOWN) {
            $b = $data['breakdown'] ?? [];

            return [[
                'service_id' => $b['service_id'] ?? null,
                'pricing_mode' => 'consolidated',
                'description' => $b['description'] ?? '',
                'scope_items' => implode("\n", $this->splitLines($b['components'] ?? '')),
                'unit' => $b['unit'] ?? null,
                'quantity' => 1,
                'amount' => $b['amount'] ?? 0,
            ]];
        }

        return $data['items'] ?? [];
    }

    /**
     * The invoice "Charge For" summary. For the single-charge modes the commercial
     * title is the single source of truth (no divergent charge_for/charge_title).
     * For itemized documents an explicit override is used, otherwise invoiceDefaults
     * fills it from the selected service names.
     */
    private function resolveChargeFor(array $data): ?string
    {
        // charge_title (the SERVICE) is the single source of truth. When present it
        // drives charge_for so the two never diverge. Itemized without a title keeps
        // an explicit override, else invoiceDefaults fills it from the service names.
        return ($data['charge_title'] ?? null) ?: (($data['charge_for'] ?? null) ?: null);
    }

    private function splitLines(?string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $text) ?: [])
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
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

    /**
     * When standards are selected, they become the single source of the invoice's
     * commercial content: a generated title, and (for the single-charge modes) the
     * "Standards / Scope" list or description. Itemized rows keep carrying their own
     * content; standards only drive the snapshot + reporting there. The immutable
     * standards_snapshot is always set (null when nothing is selected).
     */
    private function applyStandardsToData(array $data, ?ServiceCategory $category, Collection $standards): array
    {
        $data['standards_snapshot'] = $this->standardsSnapshot($category, $standards);

        if ($standards->isEmpty()) {
            return $data;
        }

        $mode = $data['charge_presentation'] ?? ProformaInvoice::PRESENTATION_ITEMIZED;

        if (empty($data['charge_title'])) {
            $data['charge_title'] = $this->standardsTitle($category, $standards);
        }

        if ($mode === ProformaInvoice::PRESENTATION_BREAKDOWN) {
            // Fill-if-empty so manual component edits survive a save; a single package
            // (e.g. Environmental Parameter Testing) contributes its own parameter list.
            if (trim((string) ($data['breakdown']['components'] ?? '')) === '') {
                $data['breakdown']['components'] = implode("\n", $this->standardsScope($standards));
            }
            if (trim((string) ($data['breakdown']['description'] ?? '')) === '') {
                $data['breakdown']['description'] = $this->standardsChargeFor($category, $standards);
            }
        } elseif ($mode === ProformaInvoice::PRESENTATION_CONSOLIDATED
            && trim((string) ($data['consolidated']['description'] ?? '')) === '') {
            $data['consolidated']['description'] = $this->standardsChargeFor($category, $standards);
        } elseif ($mode === ProformaInvoice::PRESENTATION_ITEMIZED) {
            $data['items'] = $this->attachPackageScopeToItems($data['items'] ?? [], $standards);
        }

        return $data;
    }

    private function documentData(array $data): array
    {
        return collect($data)->except(['items', 'consolidated', 'breakdown', 'new_client', 'client_id', 'after_save', 'standards'])->all();
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
            'invoice_payment_terms', 'invoice_default_notes',
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
            // New model: the user enters the amount directly. Legacy payloads
            // (quantity × unit_rate) are still supported for backward safety.
            $amount = array_key_exists('amount', $item)
                ? (float) $item['amount']
                : (float) ($item['quantity'] ?? 1) * (float) ($item['unit_rate'] ?? 0);
            $subtotal += $amount;
            $items[] = [
                'service_id' => $item['service_id'] ?? null,
                'pricing_mode' => ($item['pricing_mode'] ?? null) ?: ($service?->defaultPricingMode() ?? 'separate'),
                'description' => ($item['description'] ?? '') ?: $content->serviceDescription($service, $type) ?: 'Service',
                'scope_items' => $this->scopeItems($item, $service),
                'unit' => ($item['unit'] ?? null) ?: $service?->default_unit,
                'quantity' => $item['quantity'] ?? 1,
                'unit_rate' => $item['unit_rate'] ?? $amount,
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
