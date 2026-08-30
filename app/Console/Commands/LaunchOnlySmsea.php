<?php

namespace App\Console\Commands;

use App\Models\BusinessEntity;
use Illuminate\Console\Command;

/**
 * Initial go-live safety: make SMS Environmental Alliance the only operational
 * entity by deactivating the secondary entities (they stay in the database, with
 * all history intact, and can be re-activated any time from Manage Entities or
 * with --reactivate). Idempotent. Run manually during deployment — deliberately
 * NOT a migration, so it never alters test/dev behaviour on its own.
 */
class LaunchOnlySmsea extends Command
{
    protected $signature = 'smsea:launch-only {--reactivate : Re-activate all seeded entities instead of deactivating the secondary ones}';

    protected $description = 'Deactivate secondary entities so only SMSEA is operational at launch (reversible).';

    private const SECONDARY = ['EIDIKOS', 'ECOVERITAS', 'MAXINT', 'ICQMS'];

    public function handle(): int
    {
        $smsea = BusinessEntity::query()->where('entity_code', 'SMSEA')->first();

        if (! $smsea) {
            $this->error('SMSEA entity not found. Run "php artisan db:seed --force" first.');

            return self::FAILURE;
        }

        // SMSEA must always be active and the default.
        $smsea->forceFill(['active' => true, 'is_default' => true])->save();

        if ($this->option('reactivate')) {
            $count = BusinessEntity::query()->whereIn('entity_code', self::SECONDARY)->update(['active' => true]);
            $this->info("Re-activated {$count} secondary entit(y/ies). All seeded entities are active again.");

            return self::SUCCESS;
        }

        $count = BusinessEntity::query()
            ->whereIn('entity_code', self::SECONDARY)
            ->where('active', true)
            ->update(['active' => false]);

        $this->info('SMSEA is active and default.');
        $this->info("Deactivated {$count} secondary entit(y/ies): ".implode(', ', self::SECONDARY).'.');
        $this->line('Re-activate any of them later from Manage Entities, or run: php artisan smsea:launch-only --reactivate');

        return self::SUCCESS;
    }
}
