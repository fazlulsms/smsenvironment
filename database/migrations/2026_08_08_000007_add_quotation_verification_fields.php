<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('verification_payload_version', 32)->nullable()->after('acceptance_text');
            $table->string('verification_id', 32)->nullable()->after('verification_payload_version');
            $table->string('verification_signature', 128)->nullable()->after('verification_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn([
                'verification_payload_version',
                'verification_id',
                'verification_signature',
            ]);
        });
    }
};
