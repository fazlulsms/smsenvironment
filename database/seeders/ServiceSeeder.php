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
        $services = [
            'Environmental Impact Assessment',
            'Environmental Parameter Test',
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
        ];

        foreach ($services as $name) {
            Service::query()->firstOrCreate(
                ['name' => $name],
                [
                    'category' => 'Environmental Service',
                    'short_name' => $name,
                    'default_description' => $name,
                    'default_unit' => 'Job',
                    'default_rate' => 0,
                    'is_active' => true,
                ]
            );
        }
    }
}
