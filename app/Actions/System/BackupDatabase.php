<?php

namespace App\Actions\System;

use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Create a consistent snapshot of the SQLite database using VACUUM INTO (safe
 * to run while the app is in use), prune old copies, and record the timestamp.
 * This is the clinic's safety net — the one PC holds the only data.
 */
class BackupDatabase
{
    public function handle(?string $targetDir = null, int $keep = 30): string
    {
        $connection = config('database.default');

        if ($connection !== 'sqlite') {
            throw new RuntimeException('Automatic backup currently supports SQLite only.');
        }

        $dbPath = config("database.connections.sqlite.database");
        if (! is_string($dbPath) || ! File::exists($dbPath)) {
            throw new RuntimeException("Database file not found at [{$dbPath}].");
        }

        $dir = $targetDir ?? storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $filename = 'clinic-' . now()->format('Ymd-His') . '.sqlite';
        $target = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $filename;

        // Forward slashes keep the SQL string valid on Windows.
        $sqlPath = str_replace('\\', '/', $target);
        DB::connection('sqlite')->statement("VACUUM INTO '{$sqlPath}'");

        if (! File::exists($target)) {
            throw new RuntimeException('Backup did not produce a file.');
        }

        $this->prune($dir, $keep);

        Settings::set('backup.last_succeeded_at', now()->toIso8601String(), 'backup');

        return $target;
    }

    private function prune(string $dir, int $keep): void
    {
        $files = collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sqlite'))
            ->sortByDesc(fn ($f) => $f->getFilename())
            ->values();

        $files->slice($keep)->each(fn ($f) => File::delete($f->getPathname()));
    }
}
