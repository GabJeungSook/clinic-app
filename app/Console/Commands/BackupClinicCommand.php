<?php

namespace App\Console\Commands;

use App\Actions\System\BackupDatabase;
use Illuminate\Console\Command;

class BackupClinicCommand extends Command
{
    protected $signature = 'clinic:backup {--keep=30 : Number of backups to retain}';

    protected $description = 'Create a consistent SQLite backup of the clinic database';

    public function handle(BackupDatabase $backup): int
    {
        try {
            $path = $backup->handle(keep: (int) $this->option('keep'));
        } catch (\Throwable $e) {
            $this->error('Backup failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup created: {$path}");

        return self::SUCCESS;
    }
}
