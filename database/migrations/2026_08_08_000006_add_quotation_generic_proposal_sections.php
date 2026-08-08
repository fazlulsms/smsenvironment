<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->text('quotation_scope_assessment')->nullable()->after('quotation_default_notes');
            $table->text('quotation_methodology')->nullable()->after('quotation_scope_assessment');
            $table->text('quotation_deliverables')->nullable()->after('quotation_methodology');
            $table->text('quotation_client_responsibilities')->nullable()->after('quotation_deliverables');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->text('scope_assessment')->nullable()->after('compliance_note');
            $table->text('methodology')->nullable()->after('scope_assessment');
            $table->text('deliverables')->nullable()->after('methodology');
            $table->text('client_responsibilities')->nullable()->after('deliverables');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn([
                'scope_assessment',
                'methodology',
                'deliverables',
                'client_responsibilities',
            ]);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'quotation_scope_assessment',
                'quotation_methodology',
                'quotation_deliverables',
                'quotation_client_responsibilities',
            ]);
        });
    }
};
