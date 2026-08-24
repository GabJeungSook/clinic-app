<?php

namespace App\Actions\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;

/**
 * Replace the live SQLite database with a chosen backup snapshot. A safety
 * backup of the current data is taken first, then the connection is closed and
 * the file swapped in place (stale WAL/SHM sidecars are removed so they can't be
 * replayed over the restored file). The caller should send the user back to log
 * in, since database-backed sessions are part of what gets replaced.
 */
class RestoreDatabase
{
    public function __construct(private readonly BackupDatabase $backup) {}

    public function handle(string $sourcePath): void
    {
        if (config('database.default') !== 'sqlite') {
            throw new RuntimeException('Restore currently supports SQLite only.');
        }

        $live = config('database.connections.sqlite.database');
        if (! is_string($live) || $live === '') {
            throw new RuntimeException('Live database path is not configured.');
        }

        if (! File::exists($sourcePath)) {
            throw new RuntimeException('Backup file not found.');
        }

        $this->assertValidDatabase($sourcePath);

        // Snapshot the current data before we overwrite it (undo path).
        $this->backup->handle();

        // Close the connection so the file handle is released before we swap it.
        DB::disconnect('sqlite');

        if (! @File::copy($sourcePath, $live)) {
            throw new RuntimeException('Could not write the restored database (file in use?).');
        }

        // Remove any write-ahead-log sidecars belonging to the old database.
        foreach (['-wal', '-shm'] as $ext) {
            $sidecar = $live . $ext;
            if (File::exists($sidecar)) {
                File::delete($sidecar);
            }
        }
    }

    /** Ensure the source is a real, uncorrupted SQLite database. */
    private function assertValidDatabase(string $path): void
    {
        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $ok = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $hasMigrations = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='migrations'")->fetchColumn();
        } catch (\Throwable $e) {
            throw new RuntimeException('That file is not a valid database.');
        }

        if ($ok !== 'ok' || (int) $hasMigrations === 0) {
            throw new RuntimeException('That backup failed its integrity check and was not restored.');
        }
    }
}
