<?php

namespace App\Console\Commands;

use App\Actions\System\ReconcileCaches;
use Illuminate\Console\Command;

class ReconcileCachesCommand extends Command
{
    protected $signature = 'clinic:reconcile';

    protected $description = 'Recompute cached stock/paid values from their ledgers and report drift';

    public function handle(ReconcileCaches $reconcile): int
    {
        $result = $reconcile->handle();

        $this->info(sprintf(
            'Checked %d items, %d batches, %d invoices.',
            $result['items'], $result['batches'], $result['invoices'],
        ));

        if (empty($result['drift'])) {
            $this->info('No drift found. Caches are consistent.');
        } else {
            $this->warn(count($result['drift']) . ' drift(s) corrected:');
            foreach ($result['drift'] as $line) {
                $this->line('  - ' . $line);
            }
        }

        return self::SUCCESS;
    }
}
