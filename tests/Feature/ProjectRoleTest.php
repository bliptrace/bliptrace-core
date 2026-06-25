<?php

declare(strict_types=1);

use G8Scope\Core\Enums\ProjectRole;

it('grants the owner every ability', function () {
    foreach (['view', 'triage', 'manage_keys', 'manage_members', 'manage_rules', 'update', 'delete'] as $ability) {
        expect(ProjectRole::Owner->can($ability))->toBeTrue();
    }
});

it('limits admin to management abilities but not delete', function () {
    expect(ProjectRole::Admin->can('manage_keys'))->toBeTrue()
        ->and(ProjectRole::Admin->can('manage_members'))->toBeTrue()
        ->and(ProjectRole::Admin->can('manage_rules'))->toBeTrue()
        ->and(ProjectRole::Admin->can('update'))->toBeTrue()
        ->and(ProjectRole::Admin->can('delete'))->toBeFalse();
});

it('limits member to view and triage', function () {
    expect(ProjectRole::Member->can('view'))->toBeTrue()
        ->and(ProjectRole::Member->can('triage'))->toBeTrue()
        ->and(ProjectRole::Member->can('manage_keys'))->toBeFalse();
});

it('limits viewer to read-only', function () {
    expect(ProjectRole::Viewer->can('view'))->toBeTrue()
        ->and(ProjectRole::Viewer->can('triage'))->toBeFalse();
});
