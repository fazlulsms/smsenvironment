<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Service Category + Standard/Scheme/Program master. These are shared
 * across every business entity (not entity-owned); documents remain entity-owned
 * and snapshot the selected standards, so this master can evolve without ever
 * altering historical documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // stable slug, e.g. ISO_MGMT
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('selection_label')->nullable(); // context UI label, e.g. "Select Standards"
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['active', 'display_order']);
        });

        Schema::create('standards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();        // short code, e.g. "ISO 9001", "GOTS"
            $table->string('slug');                    // stable per-category key for idempotent seeding
            $table->string('name');                    // full display name
            $table->string('short_name')->nullable();  // concise label for tight layouts
            $table->string('type')->nullable();        // ISO Standard, Social Audit, ...
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['service_category_id', 'slug']);
            $table->index(['active', 'display_order']);
            $table->index('code');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standards');
        Schema::dropIfExists('service_categories');
    }
};
