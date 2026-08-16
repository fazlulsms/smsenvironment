<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Store the default Environmental Impact Assessment charge wording on the master
 * record (both EIA variants) so the Quick Environmental Document shortcut reads it
 * from the catalogue instead of hard-coding it. Master data only.
 */
return new class extends Migration
{
    private string $description = 'Professional services for Environmental Impact Assessment, including assessment, relevant documentation review, data analysis and reporting.';

    public function up(): void
    {
        $categoryId = DB::table('service_categories')->where('code', 'ENVIRO_SUSTAIN')->value('id');

        if (! $categoryId) {
            return;
        }

        DB::table('standards')
            ->where('service_category_id', $categoryId)
            ->whereIn('slug', ['eia', 'environmental-impact-assessment'])
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
            ->whereIn('slug', ['eia', 'environmental-impact-assessment'])
            ->update(['description' => null, 'updated_at' => now()]);
    }
};
