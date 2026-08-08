<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->service('Environmental Impact Assessment', [
            'service_type' => Service::TYPE_STANDALONE,
            'default_description' => 'Environmental Impact Assessment',
            'quotation_scope' => 'Environmental Impact Assessment including review of relevant project information, site assessment, environmental baseline consideration, impact identification, mitigation recommendations and preparation of the final report.',
        ]);

        $this->service('Environmental Parameter Assessment', [
            'service_type' => Service::TYPE_BUNDLE,
            'default_description' => 'Environmental Parameter Assessment',
            'quotation_scope' => 'Environmental Parameter Assessment Package',
            'invoice_description' => 'Environmental Parameter Assessment Package',
        ], [
            'Ambient Air Quality Assessment',
            'Stack Emission Test',
            'Noise Level Assessment',
            'Light Level Assessment',
            'Temperature Assessment',
            'Humidity Assessment',
            'GHG Assessment / Inventory',
            'ODS Assessment / Inventory',
        ]);

        $this->service('Environmental Impact Assessment Package', [
            'service_type' => Service::TYPE_BUNDLE,
            'default_description' => 'Environmental Impact Assessment Package',
            'quotation_scope' => 'Environmental Impact Assessment Package',
            'invoice_description' => 'Environmental Impact Assessment Package',
        ], [
            'Environmental Impact Assessment',
            'Ambient Air Quality Assessment',
            'Stack Emission Test',
            'Noise Level Assessment',
            'Light Level Assessment',
            'Temperature Assessment',
            'Humidity Assessment',
            'GHG Assessment / Inventory',
            'ODS Assessment / Inventory',
        ]);

        $this->service('Environmental Management Plan', [
            'service_type' => Service::TYPE_CONSOLIDATED,
            'short_name' => 'Environmental Management Plan (EMP)',
            'default_description' => 'Environmental Management Plan (EMP) - inclusive of document review, onsite assessment, relevant data collection and analysis, identification of environmental aspects and risks, development of mitigation and monitoring measures, and preparation of the final report.',
            'quotation_scope' => 'Environmental Management Plan (EMP) - inclusive of document review, onsite assessment, relevant data collection and analysis, identification of environmental aspects and risks, development of mitigation and monitoring measures, and preparation of the final report.',
            'invoice_description' => 'Environmental Management Plan (EMP)',
        ]);

        $this->service('Energy Audit', [
            'service_type' => Service::TYPE_CONSOLIDATED,
            'default_description' => 'Energy Audit - inclusive of document and utility record review, onsite assessment, energy-use data collection, review of major energy-consuming systems and equipment, performance analysis, identification of energy-saving opportunities, recommendations, and preparation of the final report.',
            'quotation_scope' => 'Energy Audit - inclusive of document and utility record review, onsite assessment, energy-use data collection, review of major energy-consuming systems and equipment, performance analysis, identification of energy-saving opportunities, recommendations, and preparation of the final report.',
            'invoice_description' => 'Energy Audit',
        ]);

        $this->service('Environmental and Social Impact Assessment', [
            'service_type' => Service::TYPE_CONSOLIDATED,
            'default_description' => 'Environmental and Social Impact Assessment - inclusive of document review, site assessment, relevant stakeholder and data collection activities, environmental and social impact analysis, risk evaluation, mitigation recommendations, and preparation of the final report.',
            'quotation_scope' => 'Environmental and Social Impact Assessment - inclusive of document review, site assessment, relevant stakeholder and data collection activities, environmental and social impact analysis, risk evaluation, mitigation recommendations, and preparation of the final report.',
            'invoice_description' => 'Environmental and Social Impact Assessment',
        ]);

        foreach ([
            'Ambient Air Quality Test',
            'Stack Emission Test',
            'Noise Level Assessment',
            'Light Level Assessment',
            'Temperature Assessment',
            'Humidity Assessment',
            'Indoor Air Quality Assessment',
            'Water Quality Test',
            'Wastewater Test',
            'GHG Inventory',
            'ODS Inventory',
            'Industrial Hygiene Assessment',
        ] as $name) {
            $this->service($name);
        }
    }

    private function service(string $name, array $attributes = [], array $components = []): void
    {
        $service = Service::query()->firstOrCreate(
            ['name' => $name],
            [
                'category' => 'Environmental Service',
                'short_name' => $attributes['short_name'] ?? $name,
                'service_type' => $attributes['service_type'] ?? Service::TYPE_STANDALONE,
                'default_description' => $attributes['default_description'] ?? $name,
                'default_unit' => 'Job',
                'default_rate' => 0,
                'quotation_scope' => $attributes['quotation_scope'] ?? null,
                'invoice_description' => $attributes['invoice_description'] ?? null,
                'is_active' => true,
            ]
        );

        if ($components === []) {
            return;
        }

        foreach ($components as $index => $component) {
            $service->components()->firstOrCreate(
                ['name' => $component],
                ['sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }
}
