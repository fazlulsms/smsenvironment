<?php

namespace App\Support;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Standard;
use Illuminate\Support\Collection;

/**
 * A read view that unifies the two normalized master tables into one browsable
 * commercial catalogue: every global {@see Standard} (the catalogue documents +
 * AI actually use) plus the legacy {@see Service} records that have no standard
 * equivalent (deduped by name). No data is copied — this only presents both
 * tables consistently so "Master Data → Services" shows the whole catalogue.
 */
class ServiceCatalogue
{
    public const TYPES = ['Service', 'Standard', 'Program', 'Package'];

    /** Legacy services (environmental testing) group under the environmental category. */
    private const LEGACY_CATEGORY_CODE = 'ENVIRO_SUSTAIN';

    /** @return Collection<int, array> every catalogue item, standards first, then legacy-only services */
    public static function all(): Collection
    {
        $standards = Standard::query()->with('category')->get()
            ->map(fn (Standard $s) => self::fromStandard($s));

        $standardNames = $standards->pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->flip();

        $legacyCategory = ServiceCategory::query()->where('code', self::LEGACY_CATEGORY_CODE)->first();

        $services = Service::query()->withCount('components')->with('businessEntities')->get()
            ->reject(fn (Service $s) => $standardNames->has(mb_strtolower(trim($s->name)))) // standard is canonical
            ->map(fn (Service $s) => self::fromService($s, $legacyCategory));

        return $standards->concat($services)
            ->sortBy([['category_order', 'asc'], ['name', 'asc']])
            ->values();
    }

    /** Category → count over the (unfiltered) catalogue, for the filter pills. */
    public static function categoryCounts(Collection $items): Collection
    {
        return $items->groupBy('category_code')->map(fn ($group) => [
            'name' => $group->first()['category'],
            'code' => $group->first()['category_code'],
            'order' => $group->first()['category_order'],
            'count' => $group->count(),
        ])->sortBy('order')->values();
    }

    /** Apply the browse filters (search / category / type / status). */
    public static function filter(Collection $items, array $filters): Collection
    {
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $category = $filters['category'] ?? null;
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;

        return $items->filter(function (array $i) use ($search, $category, $type, $status) {
            if ($search !== '' && ! str_contains(mb_strtolower($i['name'].' '.$i['sub'].' '.$i['code']), $search)) {
                return false;
            }
            if ($category && $i['category_code'] !== $category) {
                return false;
            }
            if ($type && $i['type'] !== $type) {
                return false;
            }
            if ($status === 'active' && ! $i['active']) {
                return false;
            }
            if ($status === 'inactive' && $i['active']) {
                return false;
            }

            return true;
        })->values();
    }

    private static function fromStandard(Standard $s): array
    {
        return [
            'source' => 'standard',
            'id' => $s->id,
            'name' => $s->name,
            'sub' => (string) ($s->short_name ?: $s->code),
            'code' => (string) $s->code,
            'category' => $s->category?->name ?? 'Uncategorised',
            'category_code' => $s->category?->code ?? 'OTHER',
            'category_order' => $s->category?->display_order ?? 999,
            'type' => self::standardType($s),
            'components' => count($s->defaultScope()),
            'active' => (bool) $s->active,
            'entities' => null, // global — every entity can select it
            'edit_url' => route('catalogue-standards.edit', $s),
        ];
    }

    private static function fromService(Service $s, ?ServiceCategory $category): array
    {
        return [
            'source' => 'service',
            'id' => $s->id,
            'name' => $s->name,
            'sub' => (string) $s->short_name,
            'code' => '',
            'category' => $category?->name ?? 'Environmental & Sustainability Services',
            'category_code' => self::LEGACY_CATEGORY_CODE,
            'category_order' => $category?->display_order ?? 999,
            'type' => $s->service_type === Service::TYPE_BUNDLE ? 'Package' : 'Service',
            'components' => (int) ($s->components_count ?? 0),
            'active' => (bool) $s->is_active,
            'entities' => $s->businessEntities->where('pivot.active', true)
                ->map(fn ($e) => $e->short_name ?: $e->entity_code)->values()->all(),
            'edit_url' => route('services.edit', $s),
        ];
    }

    private static function standardType(Standard $s): string
    {
        if ($s->defaultScope() !== []) {
            return 'Package';
        }

        return match ($s->category?->code) {
            'ISO_MGMT', 'TEXTILE_CERT', 'OEKOTEX', 'FORESTRY_PAPER', 'LEATHER_FOOTWEAR' => 'Standard',
            'SOCIAL_AUDIT' => 'Program',
            default => 'Service',
        };
    }
}
