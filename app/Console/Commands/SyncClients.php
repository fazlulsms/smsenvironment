<?php

namespace App\Console\Commands;

use App\Services\ClientImportService;
use Illuminate\Console\Command;

/**
 * Production-safe, idempotent import of the real client master from
 * database/data/clients.php. Creates missing clients, fills blank fields on
 * clear single matches (preserving ids + relationships), and never deletes or
 * overwrites existing data. Safe to run repeatedly.
 */
class SyncClients extends Command
{
    protected $signature = 'smsea:sync-clients {--file= : Path to the client data file (defaults to database/data/clients.php)}';

    protected $description = 'Import/sync the real client master (idempotent, non-destructive)';

    public function handle(ClientImportService $service): int
    {
        $file = $this->option('file') ?: database_path('data/clients.php');

        if (! is_file($file)) {
            $this->error("Client data file not found: {$file}");

            return self::FAILURE;
        }

        $records = require $file;

        if (! is_array($records) || $records === []) {
            $this->error('Client data file is empty or invalid.');

            return self::FAILURE;
        }

        $this->info('Importing '.count($records).' source client records...');
        $result = $service->import($records);

        $this->table(['Outcome', 'Count'], [
            ['Created', $result['created']],
            ['Updated', $result['updated']],
            ['Unchanged', $result['unchanged']],
            ['Ambiguous (skipped for review)', count($result['ambiguous'])],
            ['Skipped (invalid)', count($result['skipped'])],
        ]);

        foreach ($result['ambiguous'] as $line) {
            $this->warn('Ambiguous: '.$line);
        }
        foreach ($result['skipped'] as $line) {
            $this->warn('Skipped: '.$line);
        }

        $this->info('Done. No clients were deleted; commercial documents untouched.');

        return self::SUCCESS;
    }
}
