<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->index(['is_active', 'is_default']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['date', 'number']);
        });

        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['date', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropIndex(['date', 'number']);
            $table->dropSoftDeletes();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['date', 'number']);
            $table->dropSoftDeletes();
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'is_default']);
            $table->dropColumn('is_default');
        });
    }
};
