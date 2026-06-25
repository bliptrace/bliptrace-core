<?php

declare(strict_types=1);

namespace G8scope\Core\Reporting;

use G8scope\Core\Contracts\ReportGenerator;
use G8scope\Core\Enums\ReportType;
use G8scope\Core\Models\Issue;
use Illuminate\Support\Facades\Storage;

/**
 * Generates a report snapshot from PG (and, in the app, CH) to the configured disk
 * (spec §16). CSV is a real export; PDF is a minimal valid PDF summary. Returns the
 * stored path.
 */
class FilesystemReportGenerator implements ReportGenerator
{
    public function __construct(private readonly ?string $disk = null) {}

    public function generate(int $projectId, ReportType $type, array $options = []): string
    {
        $rows = Issue::where('project_id', $projectId)
            ->orderByDesc('occurrences_count')
            ->limit($options['limit'] ?? 100)
            ->get(['uuid', 'type', 'status', 'level', 'occurrences_count', 'last_seen_at']);

        $path = sprintf('g8scope/reports/%d-%s.%s', $projectId, uniqid(), $type->value);
        $content = $type === ReportType::Csv
            ? $this->csv($rows->toArray())
            : $this->pdf($projectId, $rows->count());

        Storage::disk($this->disk ?? config('filesystems.default'))->put($path, $content);

        return $path;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function csv(array $rows): string
    {
        $out = "uuid,type,status,level,occurrences,last_seen\n";
        foreach ($rows as $r) {
            $out .= sprintf(
                "%s,%s,%s,%s,%d,%s\n",
                $r['uuid'], $r['type'], $r['status'], $r['level'],
                $r['occurrences_count'], $r['last_seen_at'] ?? ''
            );
        }

        return $out;
    }

    private function pdf(int $projectId, int $count): string
    {
        $text = "g8scope report - project {$projectId} - {$count} issues";
        // Minimal single-page PDF.
        $body = "BT /F1 14 Tf 72 720 Td ({$text}) Tj ET";
        $objs = [
            '1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj',
            '2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj',
            '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Resources<</Font<</F1 5 0 R>>>>/Contents 4 0 R>>endobj',
            '4 0 obj<</Length '.strlen($body).'>>stream'."\n".$body."\nendstream endobj",
            '5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj',
        ];

        return "%PDF-1.4\n".implode("\n", $objs)."\ntrailer<</Root 1 0 R>>\n%%EOF";
    }
}
