<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('organization_name')->default('SMS Environmental Alliance');
            $table->string('logo_path')->nullable();
            $table->string('tagline')->nullable();
            $table->text('office_address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('default_currency')->default('BDT');
            $table->string('prepared_by_name')->nullable();
            $table->string('prepared_by_designation')->nullable();
            $table->text('default_payment_terms')->nullable();
            $table->text('footer_text')->nullable();
            $table->text('pdf_note')->nullable();
            $table->string('quotation_number_format')->default('SMSEA/QT/{YYYY}/{####}');
            $table->string('invoice_number_format')->default('SMSEA/PI/{YYYY}/{####}');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
