<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Foundation\Http\Events\RequestHandled;

/** @internal */
final readonly class DecisionFlushes
{
    private const array EVENTS = ['eloquent.saved: *', 'eloquent.deleted: *', TransactionRolledBack::class, RequestHandled::class];

    public function __construct(
        private Container $container,
        private FrameworkTables $tables,
    ) {}

    public function listen(Dispatcher $events): void
    {
        $events->listen(self::EVENTS, function (): void {
            $this->flush();
        });

        $events->listen(QueryExecuted::class, function (QueryExecuted $query): void {
            $this->flushAfter($query);
        });
    }

    public function flush(): void
    {
        if ($this->container->resolved(DecisionMemo::class)) {
            $this->container
                ->make(DecisionMemo::class)
                ->forget();
        }
    }

    private function flushAfter(QueryExecuted $query): void
    {
        if (! $this->container->resolved(DecisionMemo::class)) {
            return;
        }

        $memo = $this->container->make(DecisionMemo::class);

        if ($memo->isEmpty() || WriteStatement::reads($query->sql) || $this->writesFrameworkTable($query)) {
            return;
        }

        $memo->forget();
    }

    private function writesFrameworkTable(QueryExecuted $query): bool
    {
        $table = WriteStatement::table($query->sql);

        return $table !== null && $this->tables->holds($table, $query->connection);
    }
}
