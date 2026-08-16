<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two Environmental Impact Assessment options under Environmental & Sustainability
 * Services: the existing plain one becomes "… (Single)" (no scope), and a package
 * variant carrying the seven environmental parameters is added. Master data only —
 * documents snapshot their scope, so historical invoices are unaffected.
 */
return new class extends Migration
{
    private array $scope = [
        'Ambient Air Quality Assessment',
        'Stack Emission Test',
        'Noise Level Assessment',
        'Light Level Assessment',
        'Temperature Assessment',
        'Humidity Assessment',
        'ODS Assessment / Inventory',
    ];

    public function up(): void
    {
        $categoryId = DB::table('service_categories')->where('code', 'ENVIRO_SUSTAIN')->value('id');

        if (! $categoryId) {
            return;
        }

        // 1) The existing single EIA (slug "eia") is relabelled.
        DB::table('standards')->where('service_category_id', $categoryId)->where('slug', 'eia')
            ->update(['name' => 'Environmental Impact Assessment (Single)']);

        // 2) A package EIA carrying the seven parameters (idempotent).
        DB::table('standards')->updateOrInsert(
            ['service_category_id' => $categoryId, 'slug' => 'environmental-impact-assessment'],
            [
                'name' => 'Environmental Impact Assessment',
                'code' => null,
                'short_name' => null,
                'type' => 'Environmental Service',
                'default_scope' => implode("\n", $this->scope),
                'active' => true,
                'display_order' => 99,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $categoryId = DB::table('service_categories')->where('code', 'ENVIRO_SUSTAIN')->value('id');

        if (! $categoryId) {
            return;
        }

        DB::table('standards')->where('service_category_id', $categoryId)->where('slug', 'eia')
            ->update(['name' => 'Environmental Impact Assessment']);
        DB::table('standards')->where('service_category_id', $categoryId)->where('slug', 'environmental-impact-assessment')->delete();
    }
};
