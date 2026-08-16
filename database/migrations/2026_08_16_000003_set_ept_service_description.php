<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Store a default charge wording on the Environmental Parameter Testing master
 * record so a consolidated (single-fee) presentation of that package reads from
 * the catalogue instead of a hard-coded string. Master data only.
 */
return new class extends Migration
{
    private string $description = 'Professional services for Environmental Parameter Testing, including on-site sampling, laboratory analysis and reporting.';

    public function up(): void
    {
        $categoryId = DB::table('service_categories')->where('code', 'ENVIRO_SUSTAIN')->value('id');

        if (! $categoryId) {
            return;
        }

        DB::table('standards')
            ->where('service_category_id', $categoryId)
            ->where('slug', 'environmental-parameter-testing')
            ->whereNull('description')
            ->update(['description' => $this->description, 'updated_at' => now()]);
    }

    public function down(): void
    {
        $categoryId = DB::table('service_categories')->where('code', 'ENVIRO_SUSTAIN')->value('id');

        if (! $categoryId) {
            return;
        }

        DB::table('standards')
            ->where('service_category_id', $categoryId)
            ->where('slug', 'environmental-parameter-testing')
            ->update(['description' => null, 'updated_at' => now()]);
    }
};
