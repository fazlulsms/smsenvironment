<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Office-side handling for public inquiries: a workflow status and (for future
 * multi-entity support) the owning business entity. Public submissions default
 * to "new" and are associated with SMSEA internally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_inquiries', function (Blueprint $table) {
            $table->string('status')->default('new')->after('source');
            $table->unsignedBigInteger('business_entity_id')->nullable()->after('id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('service_inquiries', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'business_entity_id']);
        });
    }
};
