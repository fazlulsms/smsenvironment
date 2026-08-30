<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The smsea:backup command. These do not touch the application database (the
 * SQLite branch copies a file), so RefreshDatabase is intentionally not used.
 */
class BackupCommandTest extends TestCase
{
    public function test_backup_creates_a_timestamped_sqlite_file(): void
    {
        $dbFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smsea_bk_db_'.uniqid().'.sqlite';
        file_put_contents($dbFile, 'SQLite format 3'."\0"); // stand-in DB file to copy
        config([
            'database.default' => 'bk_test',
            'database.connections.bk_test' => ['driver' => 'sqlite', 'database' => $dbFile, 'prefix' => ''],
        ]);

        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'smsea_bk_out_'.uniqid();

        $this->artisan('smsea:backup', ['--path' => $dir])->assertExitCode(0);

        $files = collect(File::files($dir))->map(fn ($f) => $f->getFilename());
        $this->assertTrue(
            $files->contains(fn ($n) => (bool) preg_match('/^smsea-sqlite-\d{8}_\d{6}\.sqlite$/', $n)),
            'a timestamped sqlite backup should exist'
        );

        File::deleteDirectory($dir);
        @unlink($dbFile);
    }

    public function test_backup_fails_cleanly_for_a_missing_sqlite_file(): void
    {
        config([
            'database.default' => 'bk_missing',
            'database.connections.bk_missing' => ['driver' => 'sqlite', 'database' => sys_get_temp_dir().'/nope-'.uniqid().'.sqlite', 'prefix' => ''],
        ]);

        $this->artisan('smsea:backup', ['--path' => sys_get_temp_dir()])->assertExitCode(1);
    }
}
