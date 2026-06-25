<?php

declare(strict_types=1);

use G8scope\Core\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('provisions a default ingest key + shared endpoint binding on project create', function (): void {
    $account = Account::create(['name' => 'DevHub']);
    $project = $account->projects()->create(['name' => 'P']);

    expect($project->ingestKeys()->count())->toBe(1)
        ->and($project->binding)->not->toBeNull()
        ->and($project->binding->apiEndpoint->type->value)->toBe('shared')
        ->and($project->binding->mcpEndpoint->kind)->toBe('mcp');

    // Shared endpoints are reused for a second project in the same account.
    $account->projects()->create(['name' => 'P2']);
    expect($account->endpoints()->count())->toBe(2); // one api + one mcp, shared
});
