<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Smallest safe production database backup: a timestamped local dump.
 *
 * - SQLite: copies the database file.
 * - MySQL / MariaDB: runs mysqldump, passing credentials through a temporary
 *   --defaults-extra-file (chmod 600) so the password never appears in argv,
 *   the process list, or any log.
 *
 * It never uploads anywhere; shipping the file off-box is a separate, deliberate
 * ops step. Returns a non-zero exit code on any failure so cron/CI can detect it.
 */
class BackupDatabase extends Command
{
    protected $signature = 'smsea:backup
        {--path= : Directory to write the backup into (default: storage/app/backups)}
        {--keep= : Keep only the newest N backups in that directory (optional retention)}';

    protected $description = 'Create a timestamped local backup of the application database.';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (! is_array($config)) {
            $this->error("Unknown database connection [{$connection}].");

            return self::FAILURE;
        }

        $dir = $this->option('path') ?: storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $stamp = now()->format('Ymd_His');
        $driver = $config['driver'] ?? $connection;

        try {
            $file = match ($driver) {
                'sqlite' => $this->backupSqlite($config, $dir, $stamp),
                'mysql', 'mariadb' => $this->backupMysql($config, $dir, $stamp),
                default => throw new \RuntimeException("Backup for driver [{$driver}] is not supported by this command. Use your provider's native dump tool."),
            };
        } catch (\Throwable $e) {
            // Message is safe: connection details are never interpolated into it.
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup created: '.$file.' ('.$this->humanSize(filesize($file)).')');

        $this->applyRetention($dir);

        return self::SUCCESS;
    }

    private function backupSqlite(array $config, string $dir, string $stamp): string
    {
        $source = $config['database'] ?? null;

        if (! $source || ! is_file($source)) {
            throw new \RuntimeException('SQLite database file was not found.');
        }

        $target = $dir.DIRECTORY_SEPARATOR.'smsea-sqlite-'.$stamp.'.sqlite';

        if (! File::copy($source, $target)) {
            throw new \RuntimeException('Could not copy the SQLite database file.');
        }

        return $target;
    }

    private function backupMysql(array $config, string $dir, string $stamp): string
    {
        $binary = 'mysqldump';
        $database = $config['database'] ?? '';
        $target = $dir.DIRECTORY_SEPARATOR.'smsea-'.($database ?: 'db').'-'.$stamp.'.sql';

        // Credentials via a temp defaults file (mode 600) — never on the CLI.
        $cnf = tempnam(sys_get_temp_dir(), 'smsea_my_');
        file_put_contents($cnf, implode("\n", array_filter([
            '[client]',
            'host='.($config['host'] ?? '127.0.0.1'),
            'port='.($config['port'] ?? '3306'),
            'user='.($config['username'] ?? ''),
            isset($config['password']) && $config['password'] !== '' ? 'password="'.$config['password'].'"' : null,
        ]))."\n");
        @chmod($cnf, 0600);

        try {
            $out = fopen($target, 'wb');
            if ($out === false) {
                throw new \RuntimeException('Could not open the backup file for writing.');
            }

            $process = new Process([
                $binary,
                '--defaults-extra-file='.$cnf,
                '--single-transaction',
                '--quick',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                $database,
            ]);
            $process->setTimeout(600);
            $process->run(function ($type, $buffer) use ($out) {
                if ($type === Process::OUT) {
                    fwrite($out, $buffer);
                }
            });
            fclose($out);

            if (! $process->isSuccessful()) {
                @unlink($target);
                // Trim to avoid leaking anything unexpected into logs.
                throw new \RuntimeException('mysqldump exited with an error. Check that mysqldump is installed and the credentials are valid.');
            }
        } finally {
            @unlink($cnf);
        }

        return $target;
    }

    private function applyRetention(string $dir): void
    {
        $keep = $this->option('keep');

        if ($keep === null || ! ctype_digit((string) $keep) || (int) $keep < 1) {
            return;
        }

        $backups = collect(File::files($dir))
            ->filter(fn ($f) => str_starts_with($f->getFilename(), 'smsea-'))
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->values();

        $backups->slice((int) $keep)->each(function ($f) {
            File::delete($f->getPathname());
            $this->line('Pruned old backup: '.$f->getFilename());
        });
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), 2).' '.$units[$i];
    }
}
