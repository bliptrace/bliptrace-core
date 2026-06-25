<?php

declare(strict_types=1);

use G8Scope\Core\Enums\EndpointType;
use G8Scope\Core\Enums\Environment;
use G8Scope\Core\Enums\IssueStatus;
use G8Scope\Core\Enums\Level;
use G8Scope\Core\Enums\ProjectRole;
use G8Scope\Core\Enums\ReportType;
use G8Scope\Core\Enums\SupportStatus;

dataset('enums', [
    [IssueStatus::class],
    [Level::class],
    [Environment::class],
    [ProjectRole::class],
    [EndpointType::class],
    [ReportType::class],
    [SupportStatus::class],
]);

it('exposes label() and color() on every case', function (string $enum): void {
    foreach ($enum::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty()
            ->and($case->color())->toBeString()->not->toBeEmpty();
    }
})->with('enums');

it('has the expected issue lifecycle states', function (): void {
    expect(IssueStatus::cases())->toHaveCount(4)
        ->and(IssueStatus::Open->color())->toBe('red')
        ->and(ProjectRole::Owner->label())->toBe('Owner');
});
