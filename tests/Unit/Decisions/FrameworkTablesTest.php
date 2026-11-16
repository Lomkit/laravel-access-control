<?php

namespace Lomkit\Access\Tests\Unit\Decisions;

use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Database\SqlServerConnection;
use Lomkit\Access\Decisions\FrameworkTables;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class FrameworkTablesTest extends PackageTestCase
{
    #[Test]
    public function it_quotes_the_prefixed_table_as_each_grammar_does(): void
    {
        $tables = $this->tables(['cache' => ['stores' => ['database' => ['driver' => 'database', 'table' => 'cache']]]]);

        $this->assertTrue($tables->holds('`pre_cache`', $this->connection(MySqlConnection::class, 'app')));
        $this->assertTrue($tables->holds('"pre_cache"', $this->connection(PostgresConnection::class, 'app')));
        $this->assertTrue($tables->holds('"pre_cache"', $this->connection(SQLiteConnection::class, 'app')));
        $this->assertTrue($tables->holds('[pre_cache]', $this->connection(SqlServerConnection::class, 'app')));
        $this->assertFalse($tables->holds('`cache`', $this->connection(MySqlConnection::class, 'app')));
        $this->assertFalse($tables->holds('`pre_posts`', $this->connection(MySqlConnection::class, 'app')));
    }

    #[Test]
    public function it_quotes_a_schema_qualified_table(): void
    {
        $tables = $this->tables(['cache' => ['stores' => ['database' => ['driver' => 'database', 'table' => 'public.cache']]]]);

        $this->assertTrue($tables->holds('"public"."pre_cache"', $this->connection(PostgresConnection::class, 'app')));
        $this->assertFalse($tables->holds('"pre_cache"', $this->connection(PostgresConnection::class, 'app')));
    }

    #[Test]
    public function it_holds_each_table_on_its_own_connection_only(): void
    {
        $tables = $this->tables(['cache' => ['stores' => ['database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => 'cache_db',
            'lock_connection' => 'locks_db',
        ]]]]);

        $this->assertTrue($tables->holds('`pre_cache`', $this->connection(MySqlConnection::class, 'cache_db')));
        $this->assertFalse($tables->holds('`pre_cache`', $this->connection(MySqlConnection::class, 'app')));
        $this->assertTrue($tables->holds('`pre_cache_locks`', $this->connection(MySqlConnection::class, 'locks_db')));
        $this->assertFalse($tables->holds('`pre_cache_locks`', $this->connection(MySqlConnection::class, 'cache_db')));
    }

    #[Test]
    public function it_reads_an_empty_connection_as_the_default_one(): void
    {
        $tables = $this->tables(['session' => ['driver' => 'database', 'table' => 'sessions', 'connection' => '']]);

        $this->assertTrue($tables->holds('`pre_sessions`', $this->connection(MySqlConnection::class, 'app')));
    }

    #[Test]
    public function it_holds_the_failed_jobs_and_batches_only_for_their_database_drivers(): void
    {
        $database = $this->tables(['queue' => ['failed' => ['driver' => 'database-uuids', 'table' => 'failed_jobs']]]);
        $file = $this->tables(['queue' => ['failed' => ['driver' => 'file', 'table' => 'failed_jobs'], 'batching' => ['driver' => 'dynamodb']]]);
        $connection = $this->connection(MySqlConnection::class, 'app');

        $this->assertTrue($database->holds('`pre_failed_jobs`', $connection));
        $this->assertTrue($database->holds('`pre_job_batches`', $connection));
        $this->assertFalse($file->holds('`pre_failed_jobs`', $connection));
        $this->assertFalse($file->holds('`pre_job_batches`', $connection));
    }

    /** @param  array<string, mixed>  $config */
    private function tables(array $config): FrameworkTables
    {
        return new FrameworkTables(new Repository(['database' => ['default' => 'app'], ...$config]));
    }

    /** @param  class-string<Connection>  $class */
    private function connection(string $class, string $name): Connection
    {
        return new $class(fn (): null => null, 'app', 'pre_', ['name' => $name]);
    }
}
