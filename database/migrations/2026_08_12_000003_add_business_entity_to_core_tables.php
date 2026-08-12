<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that gain direct entity ownership. Children (items, service
     * components) inherit ownership through their parent and are not stamped.
     */
    private array $tables = [
        'clients',
        'services',
        'quotations',
        'proforma_invoices',
        'bank_accounts',
        'document_email_deliveries',
        'settings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('business_entity_id')->nullable()->constrained()->nullOnDelete();
                $blueprint->index('business_entity_id');
            });
        }

        // Entity code is snapshotted onto documents so QR verification stays
        // stable even if the entity is later renamed or reconfigured.
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('entity_code')->nullable();
        });
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->string('entity_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('entity_code'));
        Schema::table('proforma_invoices', fn (Blueprint $table) => $table->dropColumn('entity_code'));

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['business_entity_id']);
                $blueprint->dropColumn('business_entity_id');
            });
        }
    }
};
