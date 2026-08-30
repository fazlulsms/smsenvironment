<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Public-website visibility flag for catalogue items. Default false, so nothing is
 * exposed on the public site unless deliberately opted in. Only the in-scope,
 * catalogue-backed featured services are flagged here; the public site otherwise
 * renders curated content, never the full internal commercial catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standards', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('active');
        });

        $categoryId = DB::table('service_categories')->where('code', 'ENVIRO_SUSTAIN')->value('id');

        if ($categoryId) {
            DB::table('standards')
                ->where('service_category_id', $categoryId)
                ->whereIn('slug', ['eia', 'environmental-impact-assessment', 'environmental-parameter-testing'])
                ->update(['is_public' => true, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('standards', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
