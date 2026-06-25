<?php

declare(strict_types=1);

use G8scope\Core\Actions\RequestSupport;
use G8scope\Core\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('scrubs context, creates a g8desk ticket, and stores the SupportLink', function (): void {
    config()->set('g8scope.g8desk', ['base_url' => 'https://desk.test', 'token' => 't']);
    Http::fake(['desk.test/*' => Http::response(['reference' => 'DESK-2026-00042'], 201)]);

    $account = Account::create(['name' => 'DevHub']);
    $project = $account->projects()->create(['name' => 'P']);
    $issue = $project->issues()->create(['fingerprint' => 'fp', 'type' => 'E', 'message' => 'boom']);

    $link = app(RequestSupport::class)($issue, ['password' => 'hunter2', 'note' => 'urgent']);

    expect($link->reference)->toBe('DESK-2026-00042')
        ->and($link->issue_id)->toBe($issue->id);

    // PII scrubbed before crossing the boundary
    Http::assertSent(function ($request) {
        $body = $request->data();

        return ($body['context']['password'] ?? null) === '[REDACTED]';
    });
});
