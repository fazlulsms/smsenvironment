<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global Charge Particular library: reusable commercial wording ("Audit Fee",
 * "Travel & Operational Cost", …) shared across every business entity. It only
 * helps users prepare a document — selected wording is copied into the document's
 * own scope_items/description snapshot, so renaming a master never changes history.
 * Deliberately no price column: amounts are always document-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_particulars', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();          // canonical display wording
            $table->string('category')->nullable();    // grouping label (a simple string is enough)
            $table->text('search_keywords')->nullable(); // aliases / acronyms for forgiving search
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_particulars');
    }
};
