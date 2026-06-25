<?php

declare(strict_types=1);

namespace G8scope\Core\Actions;

use G8scope\Core\Contracts\ReportGenerator;
use G8scope\Core\Enums\ReportType;
use G8scope\Core\Models\Project;
use G8scope\Core\Models\Report;

/** Generate a report artifact and record it (spec §16, §18). */
class GenerateReport
{
    public function __construct(private readonly ReportGenerator $generator) {}

    public function __invoke(Project $project, ReportType $type, array $options = []): Report
    {
        $path = $this->generator->generate($project->id, $type, $options);

        return $project->reports()->create([
            'type' => $type->value,
            'path' => $path,
            'generated_at' => now(),
        ]);
    }
}
