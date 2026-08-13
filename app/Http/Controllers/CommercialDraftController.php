<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Client;
use App\Models\ServiceCategory;
use App\Services\Commercial\CommercialDraftResolver;
use App\Services\Commercial\CommercialInstructionExtractor;
use App\Support\CurrentEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AI Commercial Draft assistant. Flow: instruction → Gemini extraction → Laravel
 * resolver → review → apply. It never issues a document: Apply only prefills the
 * normal create form (via flashed old input), so numbering, validation, snapshots,
 * QR and email stay in the deterministic workflow. No document number is consumed,
 * no client is auto-created, no email is sent.
 */
class CommercialDraftController extends Controller
{
    public function index(): View
    {
        return $this->page(null, '');
    }

    public function analyze(Request $request, CommercialInstructionExtractor $extractor, CommercialDraftResolver $resolver): View
    {
        $data = $request->validate([
            'entity_id' => ['required', 'exists:business_entities,id'],
            'instruction' => ['required', 'string', 'max:8000'],
        ]);

        $result = $extractor->extract($data['instruction']);
        $draft = $result['ok'] ? $resolver->resolve($result['data'], (int) $data['entity_id']) : null;

        // On failure we keep the pasted text and never block the manual workflow.
        return $this->page($draft, $data['instruction'], (int) $data['entity_id'], $result['message'], $result['ok']);
    }

    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity_id' => ['required', 'exists:business_entities,id'],
            'target' => ['required', 'in:quotation,proforma_invoice'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'new_client_name' => ['nullable', 'string', 'max:255'],
            'new_client_email' => ['nullable', 'email', 'max:255'],
            'new_client_contact' => ['nullable', 'string', 'max:255'],
            'service_category_id' => ['nullable', 'exists:service_categories,id'],
            'standards' => ['nullable', 'array'],
            'standards.*' => ['integer', 'exists:standards,id'],
            'charge_presentation' => ['nullable', 'in:consolidated,component_breakdown,itemized'],
            'charge_title' => ['nullable', 'string', 'max:255'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:8'],
            'conversion_rate' => ['nullable', 'numeric', 'min:0'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
            'description' => ['nullable', 'string'],
            'components' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'item_description' => ['nullable', 'array'],
            'item_amount' => ['nullable', 'array'],
        ]);

        $entityId = (int) $data['entity_id'];
        // The selected entity is authoritative — the create form, numbering, banks
        // and settings all follow it.
        app(CurrentEntity::class)->switchTo($entityId);

        // Security: a bank must belong to the selected entity; otherwise drop it.
        $bankId = $data['bank_account_id'] ?? null;
        if ($bankId && ! BankAccount::query()->forEntity($entityId)->whereKey($bankId)->exists()) {
            $bankId = null;
        }

        $isInvoice = $data['target'] === 'proforma_invoice';
        $mode = $data['charge_presentation'] ?? 'consolidated';

        $prefill = array_filter([
            'client_id' => $data['client_id'] ?? null,
            'service_category_id' => $data['service_category_id'] ?? null,
            'standards' => $data['standards'] ?? [],
            'charge_title' => $data['charge_title'] ?? null,
            'bank_account_id' => $bankId,
        ], fn ($v) => $v !== null && $v !== []);

        // No matched client → prefill the quick-client fields (never auto-created).
        if (empty($prefill['client_id']) && filled($data['new_client_name'] ?? null)) {
            $prefill['new_client'] = array_filter([
                'company_name' => $data['new_client_name'],
                'email' => $data['new_client_email'] ?? null,
                'contact_person' => $data['new_client_contact'] ?? null,
            ]);
        }

        $rows = $this->itemRows($data);

        if ($isInvoice) {
            $prefill['charge_presentation'] = $mode;
            $prefill += array_filter([
                'site_name' => $data['site_name'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'currency' => $data['currency'] ?? null,
                'conversion_rate' => $data['conversion_rate'] ?? null,
            ], fn ($v) => $v !== null);

            if ($mode === 'consolidated') {
                $prefill['consolidated'] = array_filter(['description' => $data['description'] ?? null, 'amount' => $data['amount'] ?? null], fn ($v) => $v !== null);
            } elseif ($mode === 'component_breakdown') {
                $prefill['breakdown'] = array_filter(['components' => $data['components'] ?? null, 'amount' => $data['amount'] ?? null], fn ($v) => $v !== null);
            } elseif ($rows) {
                $prefill['items'] = $rows;
            }
        } else {
            // Quotations are itemized-only — fold any mode into line rows.
            $prefill['items'] = $rows ?: [[
                'description' => $data['charge_title'] ?? ($data['description'] ?? 'Professional Services'),
                'amount' => $data['amount'] ?? 0,
            ]];
        }

        $request->session()->flashInput($prefill);

        return redirect()->route($isInvoice ? 'proforma-invoices.create' : 'quotations.create')
            ->with('status', 'Draft applied — review every field, then Save or Preview to issue the document.');
    }

    /** Build itemized rows from the review form (description[]+amount[]). */
    private function itemRows(array $data): array
    {
        $rows = [];
        foreach (($data['item_description'] ?? []) as $i => $desc) {
            if (trim((string) $desc) === '') {
                continue;
            }
            $rows[] = ['description' => $desc, 'amount' => (float) ($data['item_amount'][$i] ?? 0)];
        }

        return $rows;
    }

    private function page(?array $draft, string $instruction, ?int $entityId = null, ?string $message = null, bool $ok = true): View
    {
        $entityId ??= app(CurrentEntity::class)->id();

        return view('ai_draft.index', [
            'entities' => app(CurrentEntity::class)->options(),
            'currentEntityId' => $entityId,
            'draft' => $draft,
            'instruction' => $instruction,
            'analyzeMessage' => $message,
            'analyzeOk' => $ok,
            // Review selects (only needed once a draft exists).
            'clients' => $draft ? Client::query()->orderBy('company_name')->get(['id', 'company_name']) : collect(),
            'banks' => $draft && $entityId ? BankAccount::query()->forEntity($entityId)->where('is_active', true)->orderBy('bank_name')->get(['id', 'bank_name']) : collect(),
            'serviceCategories' => $draft ? ServiceCategory::query()->active()->ordered()->get(['id', 'name']) : collect(),
        ]);
    }
}
