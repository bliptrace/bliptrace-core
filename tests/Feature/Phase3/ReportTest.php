<?php

declare(strict_types=1);

use G8Scope\Core\Actions\GenerateReport;
use G8Scope\Core\Enums\ReportType;
use G8Scope\Core\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    $account = Account::create(['name' => 'DevHub']);
    $this->project = $account->projects()->create(['name' => 'P']);
    $this->project->issues()->create(['fingerprint' => 'fp1', 'type' => 'RuntimeException', 'occurrences_count' => 12]);
});

it('generates a CSV report and records it', function (): void {
    $report = app(GenerateReport::class)($this->project, ReportType::Csv);

    expect($report->type)->toBe(ReportType::Csv)
        ->and($report->generated_at)->not->toBeNull();
    Storage::disk('local')->assertExists($report->path);
    expect(Storage::disk('local')->get($report->path))->toContain('RuntimeException');
});

it('generates a valid-ish PDF report', function (): void {
    $report = app(GenerateReport::class)($this->project, ReportType::Pdf);
    expect(Storage::disk('local')->get($report->path))->toStartWith('%PDF-');
});
