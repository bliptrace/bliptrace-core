<?php

declare(strict_types=1);

namespace G8Scope\Core\Console;

use G8Scope\Core\Actions\MaintainOccurrencePartitions;
use Illuminate\Console\Command;

class MaintainOccurrencesCommand extends Command
{
    protected $signature = 'g8scope:occurrences:maintain {--dry-run : Report what would be created, dropped and deleted without changing anything}';

    protected $description = 'Create upcoming occurrence partitions, drop those past retention and delete rows past their project retention';

    public function handle(MaintainOccurrencePartitions $maintain): int
    {
        $result = $maintain(dryRun: (bool) $this->option('dry-run'));

        if ($result['dry_run']) {
            $this->warn('Dry run: nothing was changed.');
        }
        $this->info('Retention cutoff: '.$result['cutoff']);
        $this->line('Created: '.(implode(', ', $result['created']) ?: 'none'));
        $this->line('Dropped: '.(implode(', ', $result['dropped']) ?: 'none'));
        $this->line('Rows past project retention deleted: '.$result['deleted']);

        return self::SUCCESS;
    }
}
