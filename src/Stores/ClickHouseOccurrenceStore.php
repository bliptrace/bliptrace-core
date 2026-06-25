<?php

declare(strict_types=1);

namespace G8scope\Core\Stores;

use ClickHouseDB\Client;
use G8scope\Core\Contracts\OccurrenceStore;
use G8scope\Core\Contracts\OccurrenceWriter;

/**
 * Occurrences in ClickHouse (spec §8): append-only, one issue groups thousands.
 * Implements both the read contract (OccurrenceStore) and the write side
 * (OccurrenceWriter) — production writes come from the Go api, but core owns the
 * schema + read queries and can write for the app/tests.
 */
class ClickHouseOccurrenceStore implements OccurrenceStore, OccurrenceWriter
{
    private const TABLE = 'occurrences';

    private const COLUMNS = [
        'project_id', 'issue_uuid', 'fingerprint', 'event_id',
        'level', 'environment', 'release', 'message', 'occurred_at', 'payload',
    ];

    public function __construct(private readonly Client $client) {}

    /** Build from a config array: host, port, username, password, database. */
    public static function fromConfig(array $config): self
    {
        $client = new Client([
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => $config['port'] ?? 8123,
            'username' => $config['username'] ?? 'default',
            'password' => $config['password'] ?? '',
        ]);
        $client->database($config['database'] ?? 'g8scope');
        $client->setTimeout(5);

        return new self($client);
    }

    public function migrate(): void
    {
        $this->client->write('
            CREATE TABLE IF NOT EXISTS '.self::TABLE.' (
                project_id   UInt64,
                issue_uuid   String,
                fingerprint  String,
                event_id     String,
                level        LowCardinality(String),
                environment  LowCardinality(String),
                release      String,
                message      String,
                occurred_at  DateTime64(3),
                payload      String
            ) ENGINE = MergeTree
            ORDER BY (project_id, issue_uuid, occurred_at)
        ');
    }

    public function record(array $occurrence): void
    {
        $row = [
            (int) ($occurrence['project_id'] ?? 0),
            (string) ($occurrence['issue_uuid'] ?? ''),
            (string) ($occurrence['fingerprint'] ?? ''),
            (string) ($occurrence['event_id'] ?? ''),
            (string) ($occurrence['level'] ?? 'error'),
            (string) ($occurrence['environment'] ?? ''),
            (string) ($occurrence['release'] ?? ''),
            (string) ($occurrence['message'] ?? ''),
            $occurrence['occurred_at'] ?? date('Y-m-d H:i:s'),
            is_string($occurrence['payload'] ?? null)
                ? $occurrence['payload']
                : json_encode($occurrence['payload'] ?? [], JSON_UNESCAPED_SLASHES),
        ];

        $this->client->insert(self::TABLE, [$row], self::COLUMNS);
    }

    public function forIssue(string $issueUuid, int $limit = 50): iterable
    {
        return $this->client->select(
            'SELECT * FROM '.self::TABLE.' WHERE issue_uuid = :uuid ORDER BY occurred_at DESC LIMIT :limit',
            ['uuid' => $issueUuid, 'limit' => $limit]
        )->rows();
    }

    public function sparkline(string $issueUuid, string $interval = '1h'): array
    {
        $bucket = match ($interval) {
            '1m' => 'toStartOfMinute(occurred_at)',
            '1d' => 'toStartOfDay(occurred_at)',
            default => 'toStartOfHour(occurred_at)',
        };

        $rows = $this->client->select(
            'SELECT '.$bucket.' AS bucket, count() AS count FROM '.self::TABLE.
            ' WHERE issue_uuid = :uuid GROUP BY bucket ORDER BY bucket',
            ['uuid' => $issueUuid]
        )->rows();

        return array_map(
            static fn ($r) => ['bucket' => (string) $r['bucket'], 'count' => (int) $r['count']],
            $rows
        );
    }
}
