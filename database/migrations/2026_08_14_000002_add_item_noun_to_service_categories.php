<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-item noun so the unified picker can name selections correctly: ISO/GOTS are
 * "Standards", but Environmental Impact Assessment / Environmental Parameter Testing
 * are service "Packages". Terminology/metadata only — same master, same architecture.
 */
return new class extends Migration
{
    private array $nouns = [
        'ENVIRO_SUSTAIN' => 'Package',
        'CHEMICAL_MGMT' => 'Service',
        'WORKPLACE_LABOUR' => 'Service',
        'OHS' => 'Service',
    ];

    public function up(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->string('item_noun')->nullable()->after('selection_label');
        });

        foreach ($this->nouns as $code => $noun) {
            DB::table('service_categories')->where('code', $code)->update(['item_noun' => $noun]);
        }

        // Environmental services are packages/services, not "standards".
        DB::table('service_categories')->where('code', 'ENVIRO_SUSTAIN')
            ->update(['selection_label' => 'Select Services / Packages']);
    }

    public function down(): void
    {
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropColumn('item_noun');
        });
    }
};
