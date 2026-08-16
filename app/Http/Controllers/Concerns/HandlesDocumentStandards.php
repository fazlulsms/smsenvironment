<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ServiceCategory;
use App\Models\Standard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared Service Category + Standards handling for quotations and proforma
 * invoices: resolve the selection, generate deterministic commercial wording,
 * build the immutable document snapshot, and keep the reporting pivot in sync.
 * The master data drives content; the document snapshot is the source of truth
 * for rendering, so history never changes when the master changes.
 */
trait HandlesDocumentStandards
{
    /** Ordered, active standards for the submitted ids (order preserved as sent). */
    protected function selectedStandards(array $data): Collection
    {
        $ids = collect($data['standards'] ?? [])->filter()->map(fn ($id) => (int) $id)->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $byId = Standard::query()->whereIn('id', $ids->all())->get()->keyBy('id');

        return $ids->map(fn ($id) => $byId->get($id))->filter()->values();
    }

    protected function standardsCategory(array $data): ?ServiceCategory
    {
        return empty($data['service_category_id'])
            ? null
            : ServiceCategory::query()->find($data['service_category_id']);
    }

    /**
     * Rebuild [category, standards] from a stored snapshot (e.g. when duplicating).
     * Only standards that still exist are returned for the live pivot; the snapshot
     * itself already preserves the original names regardless.
     */
    protected function standardsFromSnapshot(?array $snapshot, ?int $categoryId): array
    {
        $ids = collect($snapshot['items'] ?? [])->pluck('standard_id')->filter()->map(fn ($i) => (int) $i)->values();

        if ($ids->isEmpty()) {
            return [$categoryId ? ServiceCategory::query()->find($categoryId) : null, collect()];
        }

        $byId = Standard::query()->whereIn('id', $ids->all())->get()->keyBy('id');
        $standards = $ids->map(fn ($id) => $byId->get($id))->filter()->values();
        $category = $categoryId ? ServiceCategory::query()->find($categoryId) : null;

        return [$category, $standards];
    }

    /** Immutable snapshot stored on the document. */
    protected function standardsSnapshot(?ServiceCategory $category, Collection $standards): ?array
    {
        if ($standards->isEmpty()) {
            return null;
        }

        return [
            'category' => $category ? [
                'id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'selection_label' => $category->selectionLabel(),
            ] : null,
            'items' => $standards->values()
                ->map(fn (Standard $s, int $i) => $s->toSnapshot($i + 1))
                ->all(),
        ];
    }

    /** Deterministic commercial title, e.g. "ISO 9001, ISO 14001 & ISO 45001 Certification". */
    protected function standardsTitle(?ServiceCategory $category, Collection $standards): string
    {
        if ($standards->isEmpty()) {
            return $category?->name ?: 'Service';
        }

        $suffix = match ($category?->code) {
            'ISO_MGMT', 'TEXTILE_CERT', 'OEKOTEX', 'FORESTRY_PAPER', 'LEATHER_FOOTWEAR' => ' Certification',
            'SOCIAL_AUDIT' => ' Audit',
            default => '',
        };

        // Certification/audit families read best with short codes ("ISO 9001 … Certification");
        // service families (EIA, Environmental Parameter Testing, Higg FEM …) use full names.
        $labels = $suffix === ''
            ? $standards->map(fn (Standard $s) => $s->name)
            : $standards->map(fn (Standard $s) => $s->shortLabel());

        return trim($this->humanJoin($labels->all()).$suffix);
    }

    /** Deterministic "Charge For" default. User may override. */
    protected function standardsChargeFor(?ServiceCategory $category, Collection $standards): string
    {
        $codes = $this->humanJoin($standards->map(fn (Standard $s) => $s->shortLabel())->all(), ' and ');
        $names = $this->humanJoin($standards->map(fn (Standard $s) => $s->name)->all(), ' and ');

        return match ($category?->code) {
            'ISO_MGMT' => "Certification services for {$codes}, inclusive of applicable audit, certification and related service fees.",
            'TEXTILE_CERT', 'OEKOTEX', 'FORESTRY_PAPER', 'LEATHER_FOOTWEAR' => "Audit, certification, licence and related service fees for {$codes}.",
            'SOCIAL_AUDIT' => "Social compliance audit services for {$codes}.",
            default => "Professional services for {$names}.",
        };
    }

    /** Full display names, for the breakdown "Standards / Scope" list. */
    protected function standardNames(Collection $standards): array
    {
        return $standards->map(fn (Standard $s) => $s->name)->all();
    }

    /**
     * Package-aware itemized rows. Two things, both fill-if-empty so nothing already
     * typed/saved is overwritten:
     *  - A blank row inherits the selected standard/package NAME (so it never saves
     *    as the literal "Service"); with a single selection the name is unambiguous.
     *  - A row naming a package inherits that package's default scope, once. The
     *    saved scope_items then becomes authoritative — later master edits never
     *    change historical documents, and the row stays one priced commercial line.
     * Genuinely empty junk rows (no description, no amount, no scope) are dropped.
     */
    protected function attachPackageScopeToItems(array $items, Collection $standards): array
    {
        if ($standards->isEmpty()) {
            return $items;
        }

        $packages = $standards->filter(fn (Standard $s) => $s->defaultScope() !== []);
        $sole = $standards->count() === 1 ? $standards->first() : null;

        $rows = collect($items)->reject(fn ($item) => trim((string) ($item['description'] ?? '')) === ''
            && (float) ($item['amount'] ?? $item['unit_rate'] ?? 0) <= 0
            && trim((string) ($item['scope_items'] ?? '')) === '')->values();

        if ($rows->isEmpty()) {
            $rows = collect($items)->take(1)->values();
        }

        return $rows->map(function ($item) use ($packages, $sole) {
            $description = trim((string) ($item['description'] ?? ''));

            if ($description === '' && $sole) {
                $item['description'] = $description = $sole->name;
            }

            if (trim((string) ($item['scope_items'] ?? '')) === '') {
                $package = $packages->first(fn (Standard $s) => strcasecmp($s->name, $description) === 0);
                if ($package) {
                    $item['scope_items'] = implode("\n", $package->defaultScope());
                }
            }

            return $item;
        })->all();
    }

    /**
     * Default breakdown scope: a single package's own component list when it has
     * one (e.g. Environmental Parameter Testing → its parameters), otherwise the
     * selected standard names (e.g. the three ISO standards).
     */
    protected function standardsScope(Collection $standards): array
    {
        if ($standards->count() === 1 && ($scope = $standards->first()->defaultScope()) !== []) {
            return $scope;
        }

        return $this->standardNames($standards);
    }

    /**
     * Rebuild the document_standards reporting pivot for a saved document.
     * Recomputed from scratch each save; never used for rendering, so this is
     * always safe to regenerate.
     */
    protected function syncDocumentStandards(string $documentType, Model $document, ?ServiceCategory $category, Collection $standards): void
    {
        DB::table('document_standards')
            ->where('document_type', $documentType)
            ->where('document_id', $document->getKey())
            ->delete();

        if ($standards->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $standards->values()->map(fn (Standard $s, int $i) => [
            'document_type' => $documentType,
            'document_id' => $document->getKey(),
            'standard_id' => $s->id,
            'service_category_id' => $category?->id,
            'business_entity_id' => $document->business_entity_id,
            'sort_order' => $i + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('document_standards')->insert($rows);
    }

    /** Join like "A", "A & B", "A, B & C" (or with a custom final separator). */
    private function humanJoin(array $items, string $last = ' & '): string
    {
        $items = array_values(array_filter($items, fn ($v) => trim((string) $v) !== ''));

        if ($items === []) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        $lastItem = array_pop($items);

        return implode(', ', $items).$last.$lastItem;
    }
}
