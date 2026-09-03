<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable change-history / audit trail for material business records.
     * Additive and independent of the records it audits, so soft-deleting a
     * parent never removes its history (changed_by uses nullOnDelete).
     */
    public function up(): void
    {
        Schema::create('record_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_entity_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('action', 20); // created | updated | deleted | restored
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->json('changed_fields_json')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_histories');
    }
};
