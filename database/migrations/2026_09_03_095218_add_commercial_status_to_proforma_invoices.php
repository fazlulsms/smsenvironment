<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->string('commercial_status', 20)->default('draft')->after('total')->index();
            $table->string('lost_reason')->nullable()->after('commercial_status');
            $table->text('lost_note')->nullable()->after('lost_reason');
            $table->timestamp('status_updated_at')->nullable()->after('lost_note');
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropColumn(['commercial_status', 'lost_reason', 'lost_note', 'status_updated_at']);
        });
    }
};
