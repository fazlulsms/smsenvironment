<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-entity application theme. Colors drive CSS variables in the app UI only —
 * PDF document branding is configured separately and is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_entities', function (Blueprint $table) {
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('accent_color')->nullable();
        });

        // Sensible initial themes derived from each entity's logo/brand direction.
        $themes = [
            'SMSEA' => ['#1f6f4a', '#2da46f', '#46c98a'],
            'ECOVERITAS' => ['#14532d', '#65a30d', '#84cc16'],
            'EIDIKOS' => ['#1d4ed8', '#16a34a', '#38bdf8'],
            'ICQMS' => ['#1e3a8a', '#16a34a', '#f97316'],
            'MAXINT' => ['#0f2b46', '#0891b2', '#f59e0b'],
        ];

        foreach ($themes as $code => [$primary, $secondary, $accent]) {
            DB::table('business_entities')->where('entity_code', $code)->update([
                'primary_color' => $primary,
                'secondary_color' => $secondary,
                'accent_color' => $accent,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('business_entities', fn (Blueprint $table) => $table->dropColumn(['primary_color', 'secondary_color', 'accent_color']));
    }
};
