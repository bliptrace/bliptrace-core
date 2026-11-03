<?php

declare(strict_types=1);

namespace G8Scope\Core\Console;

use G8Scope\Core\Actions\MaintainOccurrencePartitions;
use Illuminate\Console\Command;

class MaintainOccurrencesCommand extends Command
{
    protected $signature = 'g8scope:occurrences:maintain';

    protected $description = 'Create upcoming occurrence partitions and drop those past retention';

    public function handle(MaintainOccurrencePartitions $maintain): int
    {
        $result = $maintain();

        $this->info('Retention cutoff: '.$result['cutoff']);
        $this->line('Created: '.(implode(', ', $result['created']) ?: 'none'));
        $this->line('Dropped: '.(implode(', ', $result['dropped']) ?: 'none'));

        return self::SUCCESS;
    }
}
