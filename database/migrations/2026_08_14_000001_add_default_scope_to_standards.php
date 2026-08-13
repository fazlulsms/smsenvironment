<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional default scope/components for a standard-or-service, so package-style
 * selections (e.g. Environmental Parameter Testing) can auto-populate their
 * component list in breakdown mode. One newline-separated text column; documents
 * still snapshot the resolved list, so this never affects saved history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('standards', function (Blueprint $table) {
            $table->text('default_scope')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('standards', function (Blueprint $table) {
            $table->dropColumn('default_scope');
        });
    }
};
