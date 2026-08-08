<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('service_type')->default('standalone')->after('category');
            $table->index(['service_type', 'is_active']);
        });

        Schema::create('service_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('default_price', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->string('pricing_mode')->default('separate')->after('service_id');
            $table->json('scope_items')->nullable()->after('description');
        });

        Schema::table('proforma_invoice_items', function (Blueprint $table) {
            $table->string('pricing_mode')->default('separate')->after('service_id');
            $table->json('scope_items')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('proforma_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'scope_items']);
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['pricing_mode', 'scope_items']);
        });

        Schema::dropIfExists('service_components');

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['service_type', 'is_active']);
            $table->dropColumn('service_type');
        });
    }
};
