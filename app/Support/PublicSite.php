<?php

namespace App\Support;

use App\Models\Standard;

/**
 * Curated content for the public SMS Environmental Alliance website. This is the
 * single source of truth for the marketing pages, so the site stays strictly
 * within its positioning — Environmental, Chemical, Sustainability and Training —
 * and never renders the full internal commercial catalogue. Where practical it
 * pulls a genuine detail (e.g. the Environmental Parameter Testing scope) from the
 * catalogue, but only from records explicitly flagged public.
 */
class PublicSite
{
    public const POSITIONING = 'Environmental, Chemical & Sustainability Solutions for Responsible Industry';

    public const INTRO = 'SMS Environmental Alliance supports industrial facilities with environmental assessment, testing, chemical management, sustainability improvement, compliance support and professional training.';

    public static function contact(): array
    {
        return [
            'name' => 'SMS Environmental Alliance',
            'address' => '01, Sonargaon Janapath Avenue, Sector #12, Uttara, Model Town, Dhaka-1230, Bangladesh',
            'phone' => '+8801873035178',
            'email' => 'info@smsenvironment.com',
            'website' => 'www.smsenvironment.com',
        ];
    }

    /** The four core service families shown across the site. */
    public static function families(): array
    {
        return [
            'environmental' => [
                'key' => 'environmental',
                'title' => 'Environmental Services',
                'tagline' => 'Assessment, testing and compliance support for industrial facilities.',
                'icon' => 'leaf',
                'services' => [
                    'Environmental Impact Assessment (EIA)',
                    'Initial Environmental Examination (IEE)',
                    'Environmental and Social Impact Assessment (ESIA)',
                    'Environmental Management Plan (EMP)',
                    'Environmental Compliance Audit',
                    'Environmental Parameter Testing / Assessment',
                    'Ambient Air Quality Assessment',
                    'Stack Emission Testing',
                    'Noise Level Assessment',
                    'Light Level Assessment',
                    'Temperature & Humidity Assessment',
                    'ODS Assessment / Inventory',
                    'Wastewater / ETP Assessment',
                    'Water Quality Assessment',
                    'Environmental Monitoring',
                    'Environmental Permit / Clearance Support',
                    'Resource Efficiency Assessment',
                    'Cleaner Production Assessment',
                    'Waste Management Assessment',
                    'Circularity Assessment',
                    'Life Cycle Assessment Support',
                ],
            ],
            'chemical' => [
                'key' => 'chemical',
                'title' => 'Chemical Management Services',
                'tagline' => 'Practical, industry-oriented control of chemicals from inventory to improvement.',
                'icon' => 'flask',
                'services' => [
                    'Chemical Management System Development',
                    'Chemical Risk Assessment',
                    'Chemical Inventory Review',
                    'Chemical Storage Assessment',
                    'Chemical Handling & Safety Review',
                    'Chemical Compliance Assessment',
                    'Restricted Substances Management',
                    'ZDHC-related Support (where applicable)',
                    'Chemical Management Training',
                    'Chemical Documentation Review',
                    'Chemical Risk Control Improvement',
                    'Chemical Management Gap Assessment',
                ],
            ],
            'sustainability' => [
                'key' => 'sustainability',
                'title' => 'Sustainability Services',
                'tagline' => 'Concrete improvement in energy, carbon, water, waste and resources.',
                'icon' => 'globe',
                'services' => [
                    'Energy Audit',
                    'Carbon Footprint Assessment',
                    'GHG Inventory',
                    'GHG Assessment / Verification Support',
                    'Water Footprint',
                    'Resource Efficiency',
                    'Cleaner Production',
                    'Waste Reduction',
                    'Circular Economy Assessment',
                    'Sustainability Assessment',
                    'ESG Environmental Data Support',
                    'Sustainability Reporting Support',
                    'Climate Risk Assessment',
                    'Decarbonization Support',
                    'Environmental Performance Improvement',
                    'Environmental Data Review',
                    'Sustainability Roadmap Development',
                ],
            ],
            'training' => [
                'key' => 'training',
                'title' => 'Environmental & Sustainability Training',
                'tagline' => 'In-house, public and customized capacity building.',
                'icon' => 'academic',
                'services' => [
                    'Environmental Compliance Training',
                    'Environmental Management Training',
                    'EIA / EMP Awareness',
                    'Environmental Monitoring Training',
                    'Energy Efficiency Training',
                    'Carbon & GHG Awareness',
                    'Chemical Management Training',
                    'Waste Management Training',
                    'Resource Efficiency Training',
                    'Cleaner Production Training',
                    'Sustainability Awareness',
                    'Environmental Internal Assessment Training',
                    'Environmental Legal Compliance Awareness',
                    'ETP / Wastewater Management Training',
                    'ODS Management Awareness',
                    'Climate & Sustainability Capacity Building',
                ],
            ],
        ];
    }

    /** Commercially prominent services highlighted on the home page. */
    public static function featured(): array
    {
        return [
            ['title' => 'Environmental Impact Assessment', 'desc' => 'Project and facility environmental assessment, from baseline to reporting and management planning.', 'icon' => 'leaf', 'anchor' => 'eia'],
            ['title' => 'Environmental Parameter Testing', 'desc' => 'Air, emission, noise, light, temperature, humidity and ODS assessment in one package.', 'icon' => 'gauge', 'anchor' => 'testing'],
            ['title' => 'Energy Audit', 'desc' => 'Identify and quantify energy savings across your facility and utilities.', 'icon' => 'bolt', 'anchor' => 'sustainability'],
            ['title' => 'Environmental Compliance Audit', 'desc' => 'Review facility performance against environmental requirements and good practice.', 'icon' => 'clipboard', 'anchor' => 'environmental'],
            ['title' => 'Chemical Management System', 'desc' => 'Build a working system for inventory, storage, handling and documentation.', 'icon' => 'flask', 'anchor' => 'chemical'],
            ['title' => 'Chemical Risk Assessment', 'desc' => 'Assess and prioritise chemical risks and practical controls.', 'icon' => 'shield', 'anchor' => 'chemical'],
            ['title' => 'Carbon Footprint & GHG Inventory', 'desc' => 'Measure emissions and build a defensible greenhouse-gas inventory.', 'icon' => 'globe', 'anchor' => 'sustainability'],
            ['title' => 'Wastewater / ETP Assessment', 'desc' => 'Assess effluent treatment performance and water quality.', 'icon' => 'water', 'anchor' => 'environmental'],
            ['title' => 'Resource Efficiency & Cleaner Production', 'desc' => 'Reduce resource use, waste and cost through cleaner production.', 'icon' => 'recycle', 'anchor' => 'sustainability'],
            ['title' => 'Environmental & Sustainability Training', 'desc' => 'Build in-house capability across environmental and sustainability topics.', 'icon' => 'academic', 'anchor' => 'training'],
        ];
    }

    public static function industries(): array
    {
        return ['Textile', 'Garments', 'Dyeing', 'Washing', 'Leather', 'Footwear', 'Packaging', 'Manufacturing', 'Food Processing', 'Industrial Facilities'];
    }

    public static function howWeWork(): array
    {
        return [
            ['step' => '01', 'title' => 'Understand Requirement', 'desc' => 'We clarify your facility, objective and applicable requirements.'],
            ['step' => '02', 'title' => 'Assess / Test / Review', 'desc' => 'On-site assessment, parameter testing and technical review.'],
            ['step' => '03', 'title' => 'Recommend & Support Improvement', 'desc' => 'Practical, prioritised recommendations you can act on.'],
            ['step' => '04', 'title' => 'Report / Train / Follow-up', 'desc' => 'Clear reporting, capacity building and follow-up support.'],
        ];
    }

    /** Training categories for the dedicated Training page. */
    public static function trainingCategories(): array
    {
        return [
            'Environmental Compliance', 'Chemical Management', 'Energy Efficiency', 'Carbon / GHG',
            'Waste Management', 'ETP / Wastewater', 'Resource Efficiency', 'Cleaner Production',
            'Sustainability Awareness', 'Environmental Monitoring',
        ];
    }

    /** Options for the "Service Interested In" dropdown — public scope only. */
    public static function serviceOptions(): array
    {
        return [
            'Environmental Impact Assessment (EIA)',
            'Environmental Parameter Testing',
            'Environmental Compliance Audit',
            'Wastewater / ETP Assessment',
            'Energy Audit',
            'Carbon Footprint & GHG Inventory',
            'Chemical Management System',
            'Chemical Risk Assessment',
            'Resource Efficiency & Cleaner Production',
            'Sustainability Services',
            'Environmental & Sustainability Training',
            'Other Environmental / Sustainability Service',
        ];
    }

    /**
     * The Environmental Parameter Testing scope, taken from the live catalogue
     * where the package is flagged public, with a safe curated fallback.
     */
    public static function environmentalTestingScope(): array
    {
        $standard = Standard::query()->public()
            ->whereIn('slug', ['environmental-parameter-testing'])
            ->first();

        $scope = $standard && method_exists($standard, 'defaultScope')
            ? array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $standard->default_scope))))
            : [];

        return $scope !== [] ? $scope : [
            'Ambient Air Quality Assessment',
            'Stack Emission Test',
            'Noise Level Assessment',
            'Light Level Assessment',
            'Temperature Assessment',
            'Humidity Assessment',
            'ODS Assessment / Inventory',
        ];
    }
}
