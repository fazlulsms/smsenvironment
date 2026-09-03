<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('proforma_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name')->nullable();       // snapshot for history
            $table->string('service_name')->nullable();
            $table->string('site_name')->nullable();
            $table->string('location')->nullable();
            $table->date('scheduled_from');
            $table->date('scheduled_to');
            $table->unsignedSmallInteger('assessment_days')->default(1);
            $table->string('status', 20)->default('planned')->index();
            $table->text('note')->nullable();
            $table->date('completed_date')->nullable();
            $table->date('next_reassessment_date')->nullable()->index();
            $table->boolean('reminder_enabled')->default(true);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->foreignId('reminder_sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scheduled_from', 'scheduled_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_schedules');
    }
};
