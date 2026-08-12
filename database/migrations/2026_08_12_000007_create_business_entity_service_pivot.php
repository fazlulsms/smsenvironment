<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Services become a shared global master. Availability per entity is expressed
 * through this lightweight pivot instead of duplicating service records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_entity_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->decimal('default_amount', 15, 2)->nullable();
            $table->timestamps();
            $table->unique(['business_entity_id', 'service_id']);
        });

        // Initial availability: every existing service is enabled for every
        // entity so the full library is immediately usable; entities can be
        // toggled off per service afterwards.
        $now = now();
        $serviceIds = DB::table('services')->pluck('id');
        $entityIds = DB::table('business_entities')->pluck('id');

        foreach ($serviceIds as $serviceId) {
            foreach ($entityIds as $entityId) {
                DB::table('business_entity_service')->updateOrInsert(
                    ['business_entity_id' => $entityId, 'service_id' => $serviceId],
                    ['active' => true, 'updated_at' => $now, 'created_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_entity_service');
    }
};
