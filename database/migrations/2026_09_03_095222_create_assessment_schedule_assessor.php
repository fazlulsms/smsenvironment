<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_schedule_assessor', function (Blueprint $table) {
            $table->foreignId('assessment_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessor_id')->constrained()->cascadeOnDelete();
            $table->primary(['assessment_schedule_id', 'assessor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_schedule_assessor');
    }
};
