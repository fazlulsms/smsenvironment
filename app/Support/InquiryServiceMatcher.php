<?php

namespace App\Support;

use App\Models\ServiceCategory;
use App\Models\Standard;

/**
 * Maps a free-text public inquiry "service" value to an existing catalogue
 * Standard, entirely server-side. Public input is only ever used as a search
 * hint — never as a database identifier — and no catalogue records are created.
 * Returns null when there is no reliable match, so the Office user selects
 * manually.
 */
class InquiryServiceMatcher
{
    /** Keyword (in the inquiry text) => catalogue name fragment, most specific first. */
    private const MAP = [
        'parameter testing' => 'Environmental Parameter Testing',
        'impact assessment' => 'Environmental Impact Assessment',
        'compliance audit' => 'Environmental Compliance Audit',
        'energy audit' => 'Energy Audit',
        'carbon' => 'Carbon Footprint',
        'ghg' => 'Greenhouse Gas',
        'greenhouse' => 'Greenhouse Gas',
        'wastewater' => 'Wastewater',
        'etp' => 'Wastewater',
        'cleaner production' => 'Cleaner Production',
        'resource efficiency' => 'Resource Efficiency',
        'chemical' => 'Chemical Management System',
    ];

    public static function match(?string $service): ?Standard
    {
        $service = strtolower(trim((string) $service));

        if ($service === '') {
            return null;
        }

        $categoryId = ServiceCategory::query()->where('code', 'ENVIRO_SUSTAIN')->value('id');

        if (! $categoryId) {
            return null;
        }

        foreach (self::MAP as $keyword => $nameFragment) {
            if (str_contains($service, $keyword)) {
                $standard = Standard::query()
                    ->where('service_category_id', $categoryId)
                    ->where('active', true)
                    ->where('name', 'like', '%'.$nameFragment.'%')
                    ->orderBy('display_order')
                    ->first();

                if ($standard) {
                    return $standard;
                }
            }
        }

        return null;
    }
}
