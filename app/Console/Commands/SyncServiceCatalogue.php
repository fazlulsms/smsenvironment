<?php

namespace App\Console\Commands;

use App\Models\ChargeParticular;
use App\Models\ServiceCategory;
use App\Models\Standard;
use Database\Seeders\ChargeParticularSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\StandardSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Production-safe, idempotent sync of the global service catalogue — Service
 * Categories, Standards/Programs master, Services + package components, and the
 * Charge Particular library. Wraps the existing idempotent seeders (firstOrCreate
 * on stable keys) so it only ADDS missing records and never overwrites edited
 * master data or touches commercial documents. Safe to run repeatedly.
 */
class SyncServiceCatalogue extends Command
{
    protected $signature = 'smsea:sync-service-catalogue';

    protected $description = 'Sync the global service catalogue (categories, standards, services, charge particulars) — idempotent';

    public function handle(): int
    {
        $before = $this->counts();

        $this->info('Syncing service catalogue (idempotent, additive)...');
        $this->callSilent('db:seed', ['--class' => ServiceSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => StandardSeeder::class, '--force' => true]);
        $this->callSilent('db:seed', ['--class' => ChargeParticularSeeder::class, '--force' => true]);

        // Re-assert public-website visibility for exactly the curated items (matches
        // the add_is_public migration intent) without publishing anything else.
        $enviro = ServiceCategory::query()->where('code', 'ENVIRO_SUSTAIN')->value('id');
        if ($enviro) {
            Standard::query()
                ->where('service_category_id', $enviro)
                ->whereIn('slug', ['eia', 'environmental-impact-assessment', 'environmental-parameter-testing'])
                ->update(['is_public' => true]);
        }

        $after = $this->counts();

        $this->table(
            ['Catalogue', 'Before', 'After', 'Added'],
            collect($after)->map(fn ($v, $k) => [$k, $before[$k], $v, $v - $before[$k]])->values()->all()
        );

        $this->info('Done. Existing catalogue rows and commercial documents were left untouched.');

        return self::SUCCESS;
    }

    /** @return array<string,int> */
    private function counts(): array
    {
        return [
            'Service Categories' => ServiceCategory::query()->count(),
            'Standards/Programs' => Standard::query()->count(),
            'Charge Particulars' => ChargeParticular::query()->count(),
            'Services' => DB::table('services')->count(),
        ];
    }
}
