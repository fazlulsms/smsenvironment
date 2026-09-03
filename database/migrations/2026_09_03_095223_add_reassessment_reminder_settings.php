<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('reassessment_reminder_enabled')->default(true)->after('footer_text');
            $table->unsignedSmallInteger('reassessment_reminder_lead_days')->default(30)->after('reassessment_reminder_enabled');
            $table->unsignedSmallInteger('reassessment_default_interval_months')->default(12)->after('reassessment_reminder_lead_days');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['reassessment_reminder_enabled', 'reassessment_reminder_lead_days', 'reassessment_default_interval_months']);
        });
    }
};
