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
 * Testing. It resolves the chosen service against the existing master data, builds
 * the exact payload the normal create form would submit, then hands it to the
 * normal ProformaInvoice/Quotation store — so numbering, validation, snapshots,
 * QR, verification and the redirect to the document's view page all come from the
 * deterministic workflow, never a parallel engine. "Prepare & View" saves the
 * document and opens it; editing afterwards is the normal Edit button.
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
            'presentation' => ['nullable', 'in:consolidated,component_breakdown'],
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
        // The toggle decides how the chosen service is shown; each service has a
        // sensible default when none is supplied.
        $breakdown = ($data['presentation'] ?? $service['default_presentation']) === 'component_breakdown';

        $category = ServiceCategory::query()->where('code', self::CATEGORY_CODE)->first();
        $resolve = fn (?string $slug) => $slug && $category
            ? Standard::query()->where('service_category_id', $category->id)->where('slug', $slug)->first()
            : null;
        // The package variant carries the scope (attached in breakdown / itemized);
        // the description variant carries the single-fee wording (consolidated).
        $package = $resolve($service['package_slug']);
        $descStandard = $resolve($service['desc_slug']);

        // Security: a bank must belong to SMSEA; otherwise fall back to the default.
        $bankId = $data['bank_account_id'] ?? null;
        if ($bankId && ! BankAccount::query()->forEntity($entity->id)->whereKey($bankId)->exists()) {
            $bankId = null;
        }
        $bankId ??= $this->defaultBank($entity->id)?->id;

        $amount = round((float) $data['amount'], 2);
        $isInvoice = $data['document_type'] === 'proforma_invoice';

        // The exact field set the normal create form submits.
        $payload = array_filter([
            'client_id' => $data['client_id'],
            'service_category_id' => $category?->id,
            'charge_title' => $service['title'],
            'bank_account_id' => $bankId,
            'date' => now()->toDateString(),
        ], fn ($v) => filled($v));

        // Breakdown attaches the master package so the standards backend loads its
        // configured parameters. Consolidated attaches no standard — a single
        // standard would print a redundant one-line scope.
        if ($breakdown && $package) {
            $payload['standards'] = [$package->id];
        }

        if ($isInvoice) {
            $payload['charge_presentation'] = $breakdown ? 'component_breakdown' : 'consolidated';
            $payload += array_filter([
                'currency' => $data['currency'] ?? null,
                'conversion_rate' => $data['conversion_rate'] ?? null,
                'site_name' => $data['site_name'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'vat_treatment' => $data['vat_treatment'] ?? null,
                'vat_rate' => $data['vat_rate'] ?? null,
            ], fn ($v) => filled($v));

            if ($breakdown) { // one package, its parameters, one consolidated total.
                $payload['breakdown'] = ['amount' => $amount];
            } else { // single professional fee; wording from the master record.
                $payload['consolidated'] = array_filter([
                    'description' => $descStandard?->description ?: $service['description_fallback'],
                    'amount' => $amount,
                ], fn ($v) => $v !== null);
            }
        } else {
            // Quotations are itemized-only: a single service line. In breakdown the
            // package scope is attached to that row by the standards backend on save.
            $payload['items'] = [[
                'description' => $service['title'],
                'amount' => $amount,
            ]];
        }

        // Hand the payload to the NORMAL store — same validation, numbering,
        // snapshots, QR and verification — and let it redirect to the view page.
        $request->replace($payload);
        $controller = $isInvoice ? ProformaInvoiceController::class : QuotationController::class;

        return app()->call([app($controller), 'store'], ['request' => $request]);
    }

    /**
     * The two approved services, mapped to the existing master data (never
     * duplicated). Each resolves two slugs within the Environmental &
     * Sustainability Services category: a package variant (carries the scope,
     * used for the breakdown presentation) and a description variant (carries the
     * single-fee wording, used for the consolidated presentation). Both may point
     * at the same record. `default_presentation` seeds the form toggle.
     */
    private function services(): array
    {
        return [
            'eia' => [
                'key' => 'eia',
                'label' => 'Environmental Impact Assessment (EIA)',
                'title' => 'Environmental Impact Assessment',
                'default_presentation' => 'component_breakdown',
                'desc_slug' => 'eia',
                'package_slug' => 'environmental-impact-assessment',
                'description_fallback' => 'Professional services for Environmental Impact Assessment, including assessment, relevant documentation review, data analysis and reporting.',
                'hint' => 'Impact assessment service.',
            ],
            'ept' => [
                'key' => 'ept',
                'label' => 'Environmental Parameter Testing',
                'title' => 'Environmental Parameter Testing',
                'default_presentation' => 'component_breakdown',
                'desc_slug' => 'environmental-parameter-testing',
                'package_slug' => 'environmental-parameter-testing',
                'description_fallback' => 'Professional services for Environmental Parameter Testing, including on-site sampling, laboratory analysis and reporting.',
                'hint' => 'Testing package (configured parameters).',
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
