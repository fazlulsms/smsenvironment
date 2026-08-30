<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDocumentStandards;
use App\Models\BankAccount;
use App\Models\ChargeParticular;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Services\DocumentContentService;
use App\Services\DocumentNumberService;
use App\Services\DocumentPdfService;
use App\Services\QuotationVerificationService;
use App\Support\CurrentEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuotationController extends Controller
{
    use HandlesDocumentStandards;

    public function index(): View
    {
        return view('quotations.index', [
            'quotations' => Quotation::query()
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

        return view('quotations.create', $this->formData([
            'quotation' => new Quotation([
                'client_id' => $request->integer('client_id') ?: null,
                'number' => $numbers->quotation(),
                'date' => now(),
                'intro_text' => null,
                'payment_terms' => $settings->default_payment_terms,
                'adjustment' => 0,
                'vat_treatment' => $settings->quotation_vat_treatment ?: 'exclusive',
                'vat_rate' => $settings->quotation_vat_rate,
                'show_vat_separately' => $settings->quotation_show_vat_separately ?? true,
                'include_acceptance' => $settings->quotation_include_acceptance ?? true,
            ]),
        ]));
    }

    public function store(
        Request $request,
        DocumentNumberService $numbers,
        DocumentContentService $content,
        QuotationVerificationService $verification
    ): RedirectResponse {
        $quotation = DB::transaction(function () use ($request, $numbers, $content, $verification) {
            $data = $this->validated($request);
            $standards = $this->selectedStandards($data);
            $category = $this->standardsCategory($data);
            $data = $this->applyStandardsToData($data, $category, $standards);
            $client = $this->resolveClient($data);
            $bank = $this->resolveBank($data);
            $this->validateBankForPdf($bank, $request->input('after_save') === 'pdf');
            $settings = Setting::current();
            $services = $this->selectedServices($data['items']);
            $data = $this->applyDefaults($data, $content->quotationDefaults($client, $services, $settings));
            [$items, $subtotal] = $this->items($data['items'], 'quotation', $content);
            $adjustment = (float) ($data['adjustment'] ?? 0);
            $vat = $this->vat($subtotal + $adjustment, $data);

            $quotation = Quotation::query()->create([
                ...$this->documentData($data),
                'client_id' => $client->id,
                'bank_account_id' => $bank?->id,
                'created_by' => $request->user()->id,
                'number' => $numbers->quotation(),
                'client_snapshot' => $this->clientSnapshot($client),
                'bank_snapshot' => $this->bankSnapshot($bank),
                'settings_snapshot' => $this->settingsSnapshot($settings),
                'subtotal' => $subtotal,
                'adjustment' => $adjustment,
                'vat_amount' => $vat,
                'total' => $subtotal + $adjustment + $vat,
            ]);

            $quotation->items()->createMany($items);
            $this->syncDocumentStandards('quotation', $quotation, $category, $standards);
            $verification->apply($quotation->load('items'));

            return $quotation;
        });

        if ($request->input('after_save') === 'pdf') {
            return redirect()->route('quotations.pdf', $quotation);
        }

        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation saved.');
    }

    public function show(Quotation $quotation): View
    {
        $quotation->load('client', 'bankAccount', 'items.service', 'creator', 'emailDeliveries.sender');

        return view('quotations.show', compact('quotation'));
    }

    public function edit(Quotation $quotation): View
    {
        $quotation->load('items');

        return view('quotations.edit', $this->formData(compact('quotation')));
    }

    public function update(
        Request $request,
        Quotation $quotation,
        DocumentContentService $content,
        QuotationVerificationService $verification
    ): RedirectResponse {
        DB::transaction(function () use ($request, $quotation, $content, $verification) {
            $data = $this->validated($request);
            $standards = $this->selectedStandards($data);
            $category = $this->standardsCategory($data);
            $data = $this->applyStandardsToData($data, $category, $standards);
            $client = $this->resolveClient($data);
            $bank = $this->resolveBank($data);
            $this->validateBankForPdf($bank, false);
            $settings = Setting::current();
            $services = $this->selectedServices($data['items']);
            $data = $this->applyDefaults($data, $content->quotationDefaults($client, $services, $settings));
            [$items, $subtotal] = $this->items($data['items'], 'quotation', $content);
            $adjustment = (float) ($data['adjustment'] ?? 0);
            $vat = $this->vat($subtotal + $adjustment, $data);

            $quotation->update([
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

            $quotation->items()->delete();
            $quotation->items()->createMany($items);
            $this->syncDocumentStandards('quotation', $quotation, $category, $standards);
            $verification->apply($quotation->load('items'));
        });

        return redirect()->route('quotations.show', $quotation)->with('status', 'Quotation updated.');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        $this->authorize('delete', $quotation);

        $wasIssued = $quotation->wasEmailed();

        // Soft delete only — the row (and its number) is preserved, so numbering
        // stays monotonic and any QR verification of an issued document still works.
        $quotation->delete();

        Log::warning('Quotation deleted', [
            'quotation_id' => $quotation->id,
            'number' => $quotation->number,
            'was_issued' => $wasIssued,
            'by_user_id' => auth()->id(),
        ]);

        return redirect()->route('quotations.index')->with('status', 'Quotation deleted.');
    }

    public function duplicate(
        Quotation $quotation,
        DocumentNumberService $numbers,
        QuotationVerificationService $verification
    ): RedirectResponse {
        $copy = DB::transaction(function () use ($quotation, $numbers, $verification) {
            $quotation->load('items');
            $copy = $quotation->replicate([
                'number',
                'verification_payload_version',
                'verification_id',
                'verification_signature',
            ]);
            $copy->number = $numbers->quotation();
            $copy->date = now();
            $copy->created_by = auth()->id();
            $copy->save();

            foreach ($quotation->items as $item) {
                $copy->items()->create($item->replicate(['quotation_id'])->toArray());
            }
            [$category, $standards] = $this->standardsFromSnapshot($copy->standards_snapshot, $copy->service_category_id);
            $this->syncDocumentStandards('quotation', $copy, $category, $standards);
            $verification->apply($copy->load('items'));

            return $copy;
        });

        return redirect()->route('quotations.edit', $copy)->with('status', 'Quotation duplicated with a new number.');
    }

    public function pdf(
        Quotation $quotation,
        DocumentPdfService $pdfs
    ) {
        try {
            $pdf = $pdfs->quotationPdf($quotation);
        } catch (ValidationException) {
            return redirect()->route('quotations.show', $quotation)
                ->withErrors(['bank_account_id' => 'Configure and select a valid bank account before downloading the PDF.']);
        }

        return $pdf->download($pdfs->quotationFilename($quotation));
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

    /**
     * Selected standards drive the quotation's snapshot (rendered as the Standards
     * list) and a generated title/subject default. Quotation line items still come
     * from the itemized form; standards only supply naming + the immutable snapshot.
     */
    private function applyStandardsToData(array $data, ?ServiceCategory $category, Collection $standards): array
    {
        $data['standards_snapshot'] = $this->standardsSnapshot($category, $standards);

        if ($standards->isEmpty()) {
            return $data;
        }

        $title = $this->standardsTitle($category, $standards);

        if (empty($data['charge_title'])) {
            $data['charge_title'] = $title;
        }

        if (empty($data['subject'])) {
            $data['subject'] = $title;
        }

        // Quotations are itemized — a row naming a selected package inherits its scope.
        $data['items'] = $this->attachPackageScopeToItems($data['items'] ?? [], $standards);

        return $data;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'service_category_id' => ['nullable', 'exists:service_categories,id'],
            'standards' => ['nullable', 'array'],
            'standards.*' => ['integer', 'exists:standards,id'],
            'charge_title' => ['nullable', 'string', 'max:255'],
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
            'subject' => ['nullable', 'string', 'max:255'],
            'intro_text' => ['nullable', 'string'],
            'compliance_note' => ['nullable', 'string'],
            'scope_assessment' => ['nullable', 'string'],
            'methodology' => ['nullable', 'string'],
            'deliverables' => ['nullable', 'string'],
            'client_responsibilities' => ['nullable', 'string'],
            'closing_text' => ['nullable', 'string'],
            'validity_text' => ['nullable', 'string'],
            'payment_terms' => ['nullable', 'string'],
            'vat_treatment' => ['nullable', 'in:exclusive,included,add,not_applicable'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'show_vat_separately' => ['nullable', 'boolean'],
            'vat_note' => ['nullable', 'string'],
            'ait_note' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'include_acceptance' => ['nullable', 'boolean'],
            'acceptance_text' => ['nullable', 'string'],
            'adjustment' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'after_save' => ['nullable', 'in:view,pdf'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.scope_items' => ['nullable'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0'],
            // Legacy fields kept optional for backward compatibility.
            'items.*.unit' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_rate' => ['nullable', 'numeric', 'min:0'],
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
        return collect($data)->except(['items', 'new_client', 'client_id', 'after_save', 'standards'])->all();
    }

    private function vat(float $netAmount, array $data): float
    {
        if (($data['vat_treatment'] ?? 'exclusive') !== 'add') {
            return 0.0;
        }

        $rate = (float) ($data['vat_rate'] ?? 0);

        if ($rate <= 0) {
            return 0.0;
        }

        return round(max(0, $netAmount) * $rate / 100, 2);
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
            'quotation_scope_assessment', 'quotation_methodology', 'quotation_deliverables',
            'quotation_client_responsibilities',
            'quotation_vat_treatment', 'quotation_vat_rate', 'quotation_show_vat_separately',
            'quotation_vat_note', 'quotation_ait_note', 'quotation_terms_conditions',
            'quotation_include_acceptance', 'quotation_acceptance_text',
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
            if (! array_key_exists($key, $data) || $data[$key] === null || (is_string($data[$key]) && trim($data[$key]) === '')) {
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
            // New model: amount entered directly; legacy quantity × unit_rate still honoured.
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
