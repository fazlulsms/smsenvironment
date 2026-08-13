<?php

namespace App\Services\Commercial;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\ChargeParticular;
use App\Models\Client;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Standard;
use App\Services\ClientInformationExtractor;
use App\Support\CurrentEntity;

/**
 * Turns an untrusted AI extraction into a reviewable draft by resolving every
 * value against the real masters — server-side. The AI never supplies IDs: this
 * class looks them up (entity-scoped banks, global clients/standards/particulars)
 * and Laravel performs all calculations. Statuses: matched / suggested / not_matched.
 */
class CommercialDraftResolver
{
    public const STATUS_MATCHED = 'matched';

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_NOT_MATCHED = 'not_matched';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    /**
     * @param  array  $ai  normalized extraction from CommercialInstructionExtractor
     * @param  int  $entityId  the user-selected issuing entity (authoritative)
     */
    public function resolve(array $ai, int $entityId): array
    {
        $entity = BusinessEntity::query()->findOrFail($entityId);
        $settings = $this->entitySettings($entity);

        $documentType = $this->documentType($ai['document_type'] ?? null);
        $client = $this->matchClient($ai['client_name'] ?? null);
        $standards = $this->matchStandards($ai);
        $category = $this->matchCategory($ai['service_category'] ?? null, $standards);
        $presentation = $this->presentation($ai, $standards);
        $currency = $this->currency($ai['currency'] ?? null, $settings, $defaulted);
        $rate = (new CommercialInstructionExtractor)->number($ai['conversion_rate'] ?? null);
        $bank = $this->matchBank($ai['bank_name'] ?? null, $entity);
        $totals = $this->totals($ai, $presentation['value'], $currency, $rate);

        return [
            'entity' => ['id' => $entity->id, 'name' => $entity->name, 'code' => $entity->entity_code,
                'detected' => $ai['entity_name'] ?? null, 'status' => self::STATUS_MATCHED],
            'document_type' => ['value' => $documentType,
                'status' => $documentType ? self::STATUS_MATCHED : self::STATUS_NEEDS_REVIEW],
            'client' => $client,
            'site_name' => $ai['site_name'] ?? null,
            'contact_person' => $ai['contact_person'] ?? null,
            'designation' => $ai['designation'] ?? null,
            'email' => $ai['email'] ?? null,
            'cc' => $ai['cc'] ?? null,
            'service_category' => $category,
            'standards' => $standards,
            'charge_presentation' => $presentation,
            'charge_particulars' => $this->matchParticulars($ai['charge_particulars'] ?? []),
            'itemized_rows' => $ai['itemized_rows'] ?? [],
            'currency' => ['value' => $currency, 'defaulted' => $defaulted],
            'conversion_rate' => $rate,
            'bank' => $bank,
            'reference' => $ai['reference'] ?? null,
            'notes' => $ai['notes'] ?? null,
            'totals' => $totals,
        ];
    }

    private function documentType(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }
        if (str_contains($value, 'quot')) {
            return 'quotation';
        }
        if (str_contains($value, 'pi') || str_contains($value, 'proforma') || str_contains($value, 'invoice')) {
            return 'proforma_invoice';
        }

        return null;
    }

    private function matchClient(?string $name): array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return ['id' => null, 'name' => null, 'status' => self::STATUS_NOT_MATCHED];
        }

        $normalized = (string) ClientInformationExtractor::normalizeCompany($name);
        $clients = Client::query()->get();

        // Exact normalized match, or one name is a normalized substring of the other
        // ("P.A Knit" -> "P.A. knit Composite Ltd."). Both are confident matches.
        $match = $clients->first(fn (Client $c) => ClientInformationExtractor::normalizeCompany($c->company_name) === $normalized);

        if (! $match && strlen($normalized) >= 4) {
            $match = $clients->first(function (Client $c) use ($normalized) {
                $other = (string) ClientInformationExtractor::normalizeCompany($c->company_name);

                return $other !== '' && (str_contains($other, $normalized) || str_contains($normalized, $other));
            });
        }

        return $match
            ? ['id' => $match->id, 'name' => $match->company_name, 'status' => self::STATUS_MATCHED, 'detected' => $name]
            : ['id' => null, 'name' => $name, 'status' => self::STATUS_NOT_MATCHED, 'detected' => $name];
    }

    private function matchStandards(array $ai): array
    {
        $phrases = collect(array_merge(
            $ai['selected_standards'] ?? [],
            $ai['selected_packages'] ?? [],
            $ai['selected_services'] ?? [],
        ))->filter()->unique()->values();

        $resolved = [];
        $seen = [];

        foreach ($phrases as $phrase) {
            $std = Standard::query()->active()->search($phrase)->orderBy('display_order')->first();
            if ($std && ! in_array($std->id, $seen, true)) {
                $seen[] = $std->id;
                $resolved[] = ['id' => $std->id, 'name' => $std->name, 'code' => $std->shortLabel(),
                    'category_id' => $std->service_category_id, 'detected' => $phrase, 'status' => self::STATUS_MATCHED];
            } elseif (! $std) {
                $resolved[] = ['id' => null, 'name' => $phrase, 'code' => null,
                    'category_id' => null, 'detected' => $phrase, 'status' => self::STATUS_NOT_MATCHED];
            }
        }

        return $resolved;
    }

    private function matchCategory(?string $name, array $standards): array
    {
        $name = trim((string) $name);
        if ($name !== '') {
            $cat = ServiceCategory::query()->active()
                ->where(fn ($q) => $q->where('name', 'like', "%{$name}%")->orWhere('code', 'like', "%{$name}%"))
                ->first();
            if ($cat) {
                return ['id' => $cat->id, 'name' => $cat->name, 'status' => self::STATUS_MATCHED, 'detected' => $name];
            }
        }

        // Infer from the matched standards' category.
        $catId = collect($standards)->pluck('category_id')->filter()->first();
        if ($catId && $cat = ServiceCategory::query()->find($catId)) {
            return ['id' => $cat->id, 'name' => $cat->name, 'status' => self::STATUS_SUGGESTED, 'detected' => $name ?: null];
        }

        return ['id' => null, 'name' => $name ?: null, 'status' => $name ? self::STATUS_NOT_MATCHED : self::STATUS_NEEDS_REVIEW];
    }

    private function presentation(array $ai, array $standards): array
    {
        $value = strtolower(trim((string) ($ai['charge_presentation'] ?? '')));
        $map = [
            'consolidated' => 'consolidated', 'consolidated_fee' => 'consolidated',
            'component_breakdown' => 'component_breakdown', 'breakdown' => 'component_breakdown',
            'itemized' => 'itemized', 'itemised' => 'itemized',
        ];
        foreach ($map as $needle => $mode) {
            if (str_contains($value, $needle)) {
                return ['value' => $mode, 'status' => self::STATUS_MATCHED];
            }
        }

        // Infer: priced rows -> itemized; components/particulars -> breakdown; else consolidated.
        if (! empty($ai['itemized_rows'])) {
            return ['value' => 'itemized', 'status' => self::STATUS_SUGGESTED];
        }
        if (! empty($ai['charge_particulars'])) {
            return ['value' => 'component_breakdown', 'status' => self::STATUS_SUGGESTED];
        }

        return ['value' => 'consolidated', 'status' => self::STATUS_SUGGESTED];
    }

    private function matchParticulars(array $phrases): array
    {
        return collect($phrases)->filter()->map(function (string $phrase) {
            $match = $this->bestParticular($phrase);

            return $match
                ? ['name' => $match->name, 'status' => self::STATUS_MATCHED, 'detected' => $phrase]
                : ['name' => $phrase, 'status' => self::STATUS_SUGGESTED, 'detected' => $phrase];
        })->values()->all();
    }

    /** Pick the most relevant particular: exact/prefix name beats keyword-only. */
    private function bestParticular(string $phrase): ?ChargeParticular
    {
        $phrase = trim($phrase);
        $lower = mb_strtolower($phrase);

        return ChargeParticular::query()->active()->search($phrase)->get()
            ->sortBy(fn (ChargeParticular $p) => [
                mb_strtolower($p->name) === $lower ? 0 : 1,
                str_starts_with(mb_strtolower($p->name), $lower) ? 0 : 1,
                str_contains(mb_strtolower($p->name), $lower) ? 0 : 1,
                $p->sort_order,
            ])
            ->first();
    }

    private function currency(?string $value, array $settings, ?bool &$defaulted): array|string
    {
        $defaulted = false;
        $value = strtoupper(trim((string) $value));
        $aliases = ['TAKA' => 'BDT', 'TK' => 'BDT', 'BDT' => 'BDT', 'USD' => 'USD', 'DOLLAR' => 'USD',
            'US$' => 'USD', '$' => 'USD', 'EUR' => 'EUR', 'EURO' => 'EUR', 'GBP' => 'GBP'];

        foreach ($aliases as $needle => $code) {
            if ($value === $needle || str_contains($value, $needle)) {
                return $code;
            }
        }

        $defaulted = true;

        return $settings['default_currency'] ?? 'BDT';
    }

    private function matchBank(?string $name, BusinessEntity $entity): array
    {
        $name = trim((string) $name);
        $banks = BankAccount::query()->forEntity($entity->id)->where('is_active', true)->get();

        if ($name !== '') {
            $needle = preg_replace('/\s+bank.*$/i', '', $name); // "City Bank" -> "City"
            $match = $banks->first(fn ($b) => str_contains(strtolower($b->bank_name), strtolower($name)))
                ?: $banks->first(fn ($b) => $needle && str_contains(strtolower($b->bank_name), strtolower($needle)));
            if ($match) {
                return ['id' => $match->id, 'name' => $match->bank_name, 'status' => self::STATUS_MATCHED, 'detected' => $name];
            }
        }

        $default = $banks->firstWhere('is_default', true);

        return $default
            ? ['id' => $default->id, 'name' => $default->bank_name, 'status' => self::STATUS_SUGGESTED, 'detected' => $name ?: null]
            : ['id' => null, 'name' => $name ?: null, 'status' => $name ? self::STATUS_NOT_MATCHED : self::STATUS_NEEDS_REVIEW];
    }

    /** All money maths happen here — never in the model output. */
    private function totals(array $ai, ?string $presentation, string $currency, ?float $rate): array
    {
        $num = new CommercialInstructionExtractor;

        if ($presentation === 'itemized' && ! empty($ai['itemized_rows'])) {
            $subtotal = collect($ai['itemized_rows'])->sum(fn ($r) => (float) ($r['amount'] ?? 0));
        } else {
            $subtotal = $num->number($ai['consolidated_amount'] ?? null) ?? 0.0;
        }

        $dual = $currency !== 'BDT' && $rate && $rate > 0;

        return [
            'currency' => $currency,
            'subtotal' => round((float) $subtotal, 2),
            'rate' => $dual ? $rate : null,
            'bdt_equivalent' => $dual ? round((float) $subtotal * $rate, 2) : null,
        ];
    }

    private function entitySettings(BusinessEntity $entity): array
    {
        $current = app(CurrentEntity::class);
        $previous = $current->id();
        $current->use($entity->id);
        $settings = Setting::current()->toArray();
        $current->use($previous);

        return $settings;
    }
}
