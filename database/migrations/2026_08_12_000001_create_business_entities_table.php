<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('entity_code')->unique();
            $table->string('tagline')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->string('email')->nullable();
            $table->string('finance_email')->nullable();
            $table->string('website')->nullable();
            $table->string('default_currency')->default('BDT');
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('quotation_enabled')->default(true);
            $table->boolean('proforma_invoice_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('qr_verification_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_entities');
    }
};
