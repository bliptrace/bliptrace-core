<?php

declare(strict_types=1);

namespace G8Scope\Core\Actions;

use G8Scope\Core\Models\Account;
use G8Scope\Core\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create a project in an account, idempotent on `external_ref`: when a live
 * project in the account already carries the ref, that project is returned
 * instead. The observer's unrevealable default key is removed so the caller
 * issues a revealable one with IssueIngestKey.
 */
class CreateProject
{
    /** @return array{0: Project, 1: bool} the project and whether it was created now */
    public function __invoke(Account $account, string $name, ?string $externalRef = null, ?string $environment = null): array
    {
        if ($externalRef !== null && ($existing = $this->findByRef($account, $externalRef)) !== null) {
            return [$existing, false];
        }

        try {
            $project = DB::transaction(function () use ($account, $name, $externalRef, $environment): Project {
                $project = $account->projects()->create([
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'external_ref' => $externalRef,
                    'environment' => $environment,
                ]);
                $project->ingestKeys()->delete();

                return $project;
            });
        } catch (QueryException $e) {
            // A concurrent create with the same ref won the unique index race.
            if ($externalRef !== null && ($existing = $this->findByRef($account, $externalRef)) !== null) {
                return [$existing, false];
            }

            throw $e;
        }

        return [$project, true];
    }

    private function findByRef(Account $account, string $externalRef): ?Project
    {
        return $account->projects()->where('external_ref', $externalRef)->first();
    }
}
