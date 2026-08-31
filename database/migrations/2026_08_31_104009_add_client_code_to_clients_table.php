<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business reference code for a client (e.g. SMS-BD-624), used as a stable key
     * for the client-master import/sync. Nullable and non-unique (the source list
     * reuses/omits some codes); matching also falls back to company name + email.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_code')->nullable()->after('business_entity_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['client_code']);
            $table->dropColumn('client_code');
        });
    }
};
