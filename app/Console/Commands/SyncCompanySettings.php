<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Models\BusinessEntity;
use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Production-safe, idempotent sync of baseline company/entity configuration —
 * identities (name, address, contact), logos and bank accounts — from the
 * committed database/data/company_settings.php.
 *
 * Company settings, banks and logo files live only in the database / storage,
 * so committing code alone never makes them appear in production. This command
 * bridges that gap:
 *   - upserts entity identity fields (never overwriting with a null),
 *   - copies the committed brand logo into storage/app/public/logos so DOMPDF
 *     resolves it by absolute path in production (no git-ignored upload needed),
 *   - upserts each entity's Setting row (identity + footer) and bank accounts
 *     keyed on stable identifiers.
 * It never deletes anything, never touches secrets, and is safe to re-run.
 */
class SyncCompanySettings extends Command
{
    protected $signature = 'smsea:sync-company-settings {--file= : Override path to the company settings data file}';

    protected $description = 'Sync baseline company settings, logos and bank accounts (idempotent, non-destructive)';

    private const IDENTITY_FIELDS = ['name', 'legal_name', 'short_name', 'tagline', 'address', 'phone', 'email', 'website', 'default_currency'];

    public function handle(): int
    {
        $file = $this->option('file') ?: database_path('data/company_settings.php');
        if (! is_file($file)) {
            $this->error("Company settings data file not found: {$file}");

            return self::FAILURE;
        }

        $data = require $file;
        $rows = [];
        $notes = [];

        foreach (($data['entities'] ?? []) as $code => $cfg) {
            $entity = BusinessEntity::query()->where('entity_code', $code)->first();
            if (! $entity) {
                $notes[] = "Entity {$code} not found in database — skipped (run entity migrations first).";

                continue;
            }

            foreach (self::IDENTITY_FIELDS as $field) {
                if (! empty($cfg[$field])) {
                    $entity->{$field} = $cfg[$field];
                }
            }

            $logoPath = $this->syncLogo($code, $cfg['logo'] ?? null, $notes);
            if ($logoPath) {
                $entity->logo_path = $logoPath;
            } elseif (empty($cfg['logo'])) {
                $notes[] = "Entity {$code}: no brand logo available.";
            }

            $entity->save();
            $this->syncSetting($entity, $code, $cfg, $logoPath);

            $rows[] = [$code, $entity->name, $logoPath ? 'yes' : '—'];
        }

        $bankCount = $this->syncBanks($data['banks'] ?? []);

        $this->table(['Entity', 'Name', 'Logo synced'], $rows);
        $this->info("Bank accounts upserted: {$bankCount}");
        foreach ($notes as $note) {
            $this->warn($note);
        }
        $this->info('Done. No records deleted; secrets untouched; safe to re-run.');

        return self::SUCCESS;
    }

    /** Copy the committed brand logo into storage; return its storage-relative path. */
    private function syncLogo(string $code, ?string $logo, array &$notes): ?string
    {
        if (! $logo) {
            return null;
        }

        $source = public_path('images/brand/'.$logo);
        if (! is_file($source)) {
            $notes[] = "Entity {$code}: brand logo file missing at public/images/brand/{$logo}.";

            return null;
        }

        $relative = 'logos/'.$logo;
        $dest = storage_path('app/public/'.$relative);
        if (! is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0775, true);
        }
        copy($source, $dest);

        return $relative;
    }

    private function syncSetting(BusinessEntity $entity, string $code, array $cfg, ?string $logoPath): void
    {
        $setting = Setting::withoutGlobalScopes()->firstOrNew(['business_entity_id' => $entity->id]);

        if (! $setting->exists) {
            // Minimal valid defaults for a brand-new entity settings row.
            $setting->default_currency = $cfg['default_currency'] ?? 'BDT';
            $setting->currency_major_name = 'Taka';
            $setting->currency_minor_name = 'Paisa';
            $setting->quotation_number_format = $code.'/QT/{YYYY}/{####}';
            $setting->invoice_number_format = $code.'/PI/{YYYY}/{####}';
        }

        $map = [
            'organization_name' => $cfg['name'] ?? null,
            'tagline' => $cfg['tagline'] ?? null,
            'office_address' => $cfg['address'] ?? null,
            'phone' => $cfg['phone'] ?? null,
            'email' => $cfg['email'] ?? null,
            'website' => $cfg['website'] ?? null,
            'footer_text' => $cfg['footer_text'] ?? null,
        ];
        foreach ($map as $column => $value) {
            if (! empty($value)) {
                $setting->{$column} = $value;
            }
        }
        if ($logoPath) {
            $setting->logo_path = $logoPath;
        }

        $setting->business_entity_id = $entity->id;
        $setting->save();
    }

    private function syncBanks(array $banks): int
    {
        $count = 0;
        foreach ($banks as $bank) {
            $entity = BusinessEntity::query()->where('entity_code', $bank['entity'])->first();
            if (! $entity) {
                continue;
            }

            BankAccount::withoutGlobalScopes()->updateOrCreate(
                ['account_number' => $bank['account_number']],
                [
                    'beneficiary_name' => $bank['beneficiary_name'],
                    'bank_name' => $bank['bank_name'],
                    'branch' => $bank['branch'] ?? null,
                    'routing_number' => $bank['routing_number'] ?? null,
                    'swift_code' => $bank['swift_code'] ?? null,
                    'business_entity_id' => $entity->id,
                    'is_active' => true,
                    'is_default' => (bool) ($bank['is_default'] ?? false),
                ]
            );
            $count++;
        }

        return $count;
    }
}
