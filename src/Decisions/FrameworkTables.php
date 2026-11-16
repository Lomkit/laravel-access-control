<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;

/** @internal */
final readonly class FrameworkTables
{
    private const string DATABASE = 'database';

    private const string DATABASE_UUIDS = 'database-uuids';

    private const string DYNAMODB = 'dynamodb';

    private const string LOCK_TABLE = 'cache_locks';

    private const string BATCH_TABLE = 'job_batches';

    public function __construct(private Repository $config) {}

    public function holds(string $table, Connection $connection): bool
    {
        $default = $this->config->get('database.default');
        $grammar = $connection->getQueryGrammar();

        foreach ($this->tables() as [$name, $declared]) {
            if (($name ?? $default) === $connection->getName() && $grammar->wrapTable($declared) === $table) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{?string, string}> */
    private function tables(): array
    {
        return [...$this->cacheTables(), ...$this->queueTables(), ...$this->failedJobTables(), ...$this->sessionTables()];
    }

    /** @return list<array{?string, string}> */
    private function cacheTables(): array
    {
        $tables = [];

        foreach ($this->databaseEntries('cache.stores') as $store) {
            $connection = $store['connection'] ?? null;

            $tables = [
                ...$tables,
                ...$this->table($connection, $store['table'] ?? null),
                ...$this->table($store['lock_connection'] ?? $connection, $store['lock_table'] ?? self::LOCK_TABLE),
            ];
        }

        return $tables;
    }

    /** @return list<array{?string, string}> */
    private function queueTables(): array
    {
        $tables = [];

        foreach ($this->databaseEntries('queue.connections') as $queue) {
            $tables = [...$tables, ...$this->table($queue['connection'] ?? null, $queue['table'] ?? null)];
        }

        if ($this->config->get('queue.batching.driver', self::DATABASE) === self::DYNAMODB) {
            return $tables;
        }

        return [...$tables, ...$this->table($this->config->get('queue.batching.database'), $this->config->get('queue.batching.table', self::BATCH_TABLE))];
    }

    /** @return list<array{?string, string}> */
    private function failedJobTables(): array
    {
        if (! in_array($this->config->get('queue.failed.driver', self::DATABASE), [self::DATABASE, self::DATABASE_UUIDS], true)) {
            return [];
        }

        return $this->table($this->config->get('queue.failed.database'), $this->config->get('queue.failed.table'));
    }

    /** @return list<array{?string, string}> */
    private function sessionTables(): array
    {
        if ($this->config->get('session.driver') !== self::DATABASE) {
            return [];
        }

        return $this->table($this->config->get('session.connection'), $this->config->get('session.table'));
    }

    /** @return list<array<array-key, mixed>> */
    private function databaseEntries(string $key): array
    {
        $entries = $this->config->get($key);

        if (! is_array($entries)) {
            return [];
        }

        return array_values(array_filter($entries, fn (mixed $entry): bool => is_array($entry) && ($entry['driver'] ?? null) === self::DATABASE));
    }

    /** @return list<array{?string, string}> */
    private function table(mixed $connection, mixed $table): array
    {
        if (! is_string($table) || $table === '' || ($connection !== null && ! is_string($connection))) {
            return [];
        }

        return [[$connection === '' ? null : $connection, $table]];
    }
}
