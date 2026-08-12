<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Point the Eidikos entity + its settings at the brand logo. Stored as configured
 * data (logo_path), so the invoice header, snapshot and app UI all pick it up; the
 * file can be replaced (e.g. with a raster original) without touching code.
 */
return new class extends Migration
{
    private string $logo = 'logos/eidikos-logo.svg';

    public function up(): void
    {
        $eidikosId = DB::table('business_entities')->where('entity_code', 'EIDIKOS')->value('id');

        if (! $eidikosId) {
            return;
        }

        DB::table('business_entities')->where('id', $eidikosId)->update(['logo_path' => $this->logo]);
        DB::table('settings')->where('business_entity_id', $eidikosId)->update(['logo_path' => $this->logo]);
    }

    public function down(): void
    {
        $eidikosId = DB::table('business_entities')->where('entity_code', 'EIDIKOS')->value('id');

        if (! $eidikosId) {
            return;
        }

        DB::table('business_entities')->where('id', $eidikosId)->update(['logo_path' => null]);
        DB::table('settings')->where('business_entity_id', $eidikosId)->update(['logo_path' => null]);
    }
};
