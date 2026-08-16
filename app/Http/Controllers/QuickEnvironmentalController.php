<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Client;
use App\Models\ServiceCategory;
use App\Models\Standard;
use App\Support\CurrentEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Quick Environmental Document shortcut. A faster INPUT path for the two most-used
 * SMSEA services — Environmental Impact Assessment and Environmental Parameter
 * Testing. It never saves, issues, emails or numbers anything: "Prepare" only
 * resolves the service against the existing master data, builds a prefill and
 * flashes it as old input, then redirects to the NORMAL create form where the
 * usual validation / snapshot / numbering / PDF / QR workflow takes over.
 *
 * The entity is fixed to SMSEA; no cross-entity records are ever created, and no
 * arbitrary IDs from the browser are trusted (client / bank are re-validated
 * server-side, the service must resolve to one of the two approved records).
 */
class QuickEnvironmentalController extends Controller
{
    private const ENTITY_CODE = 'SMSEA';

    private const CATEGORY_CODE = 'ENVIRO_SUSTAIN';

    public function index(Request $request): View
    {
        $entity = $this->entity();
        // Fixed entity: resolve clients / banks against SMSEA for this screen.
        app(CurrentEntity::class)->use($entity->id);

        $service = in_array($request->query('service'), ['eia', 'ept'], true)
            ? $request->query('service')
            : 'eia';

        $default = $this->defaultBank($entity->id);

        return view('quick_document.index', [
            'entity' => $entity,
            'services' => $this->services(),
            'selectedService' => $service,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
            'banks' => BankAccount::query()->forEntity($entity->id)->where('is_active', true)->orderBy('bank_name')->get(['id', 'bank_name']),
            'defaultBankId' => $default?->id,
            'hasBank' => (bool) $default,
            'currencies' => ['BDT', 'USD', 'EUR', 'GBP'],
        ]);
    }

    public function prepare(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'service' => ['required', 'in:eia,ept'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'document_type' => ['required', 'in:proforma_invoice,quotation'],
            // Advanced (all optional; sensible defaults otherwise).
            'currency' => ['nullable', 'string', 'max:8'],
            'conversion_rate' => ['nullable', 'numeric', 'min:0'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'vat_treatment' => ['nullable', 'in:exclusive,included,add,not_applicable'],
            'vat_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $entity = $this->entity();
        // The entity is authoritative — numbering, banks and settings follow SMSEA.
        app(CurrentEntity::class)->switchTo($entity->id);

        $service = $this->services()[$data['service']];
        $category = ServiceCategory::query()->where('code', self::CATEGORY_CODE)->first();
        $standard = $category
            ? Standard::query()->where('service_category_id', $category->id)->where('slug', $service['slug'])->first()
            : null;

        // Security: a bank must belong to SMSEA; otherwise fall back to the default.
        $bankId = $data['bank_account_id'] ?? null;
        if ($bankId && ! BankAccount::query()->forEntity($entity->id)->whereKey($bankId)->exists()) {
            $bankId = null;
        }
        $bankId ??= $this->defaultBank($entity->id)?->id;

        $amount = round((float) $data['amount'], 2);
        $isInvoice = $data['document_type'] === 'proforma_invoice';

        $prefill = array_filter([
            'client_id' => $data['client_id'],
            'service_category_id' => $category?->id,
            'charge_title' => $service['title'],
            'bank_account_id' => $bankId,
        ], fn ($v) => filled($v));

        // EPT attaches its master package so the standards backend loads its seven
        // configured parameters on save. EIA stays a single consolidated charge —
        // attaching a single standard would print a redundant one-line scope.
        if ($service['attach_standard'] && $standard) {
            $prefill['standards'] = [$standard->id];
        }

        if ($isInvoice) {
            $prefill['charge_presentation'] = $service['presentation'];
            $prefill += array_filter([
                'currency' => $data['currency'] ?? null,
                'conversion_rate' => $data['conversion_rate'] ?? null,
                'site_name' => $data['site_name'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'vat_treatment' => $data['vat_treatment'] ?? null,
                'vat_rate' => $data['vat_rate'] ?? null,
            ], fn ($v) => filled($v));

            if ($service['presentation'] === 'consolidated') {
                // Charge wording comes from the master record (fallback if unset).
                $prefill['consolidated'] = array_filter([
                    'description' => $standard?->description ?: $service['description_fallback'],
                    'amount' => $amount,
                ], fn ($v) => $v !== null);
            } else { // component_breakdown — one package, one consolidated total.
                $prefill['breakdown'] = ['amount' => $amount];
            }
        } else {
            // Quotations are itemized-only: a single service line. EPT's package scope
            // is attached to that row by the standards backend on save.
            $prefill['items'] = [[
                'description' => $service['title'],
                'amount' => $amount,
            ]];
        }

        $request->session()->flashInput($prefill);

        $status = 'Environmental document prepared — review every field, then Save or Preview to issue it.';
        if (! $bankId) {
            $status .= ' No active SMSEA bank was found; select a bank before saving.';
        }

        return redirect()
            ->route($isInvoice ? 'proforma-invoices.create' : 'quotations.create')
            ->with('status', $status);
    }

    /**
     * The two approved services, mapped to the existing master data (never
     * duplicated). Slugs resolve within the Environmental & Sustainability
     * Services category.
     */
    private function services(): array
    {
        return [
            'eia' => [
                'key' => 'eia',
                'label' => 'Environmental Impact Assessment (EIA)',
                'title' => 'Environmental Impact Assessment',
                'presentation' => 'consolidated',
                'slug' => 'eia',
                'attach_standard' => false,
                'description_fallback' => 'Professional services for Environmental Impact Assessment, including assessment, relevant documentation review, data analysis and reporting.',
                'hint' => 'One consolidated professional fee.',
            ],
            'ept' => [
                'key' => 'ept',
                'label' => 'Environmental Parameter Testing',
                'title' => 'Environmental Parameter Testing',
                'presentation' => 'component_breakdown',
                'slug' => 'environmental-parameter-testing',
                'attach_standard' => true,
                'description_fallback' => null,
                'hint' => 'Package with its configured parameters, one total.',
            ],
        ];
    }

    private function entity(): BusinessEntity
    {
        return BusinessEntity::query()->where('entity_code', self::ENTITY_CODE)->firstOrFail();
    }

    /** Default active SMSEA bank, resolved server-side (never a hard-coded id). */
    private function defaultBank(int $entityId): ?BankAccount
    {
        $banks = BankAccount::query()->forEntity($entityId)->where('is_active', true)->get();

        return $banks->firstWhere('is_default', true) ?? $banks->first();
    }
}
