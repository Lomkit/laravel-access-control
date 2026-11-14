<?php

namespace Lomkit\Access\Search\Freshness;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseTransactionRecord;
use WeakMap;

/** @internal */
final class AfterCommit
{
    /** @var WeakMap<DatabaseTransactionRecord, array<string, true>>|null */
    private static ?WeakMap $pending = null;

    public static function run(Connection $connection, Closure $callback): void
    {
        $transaction = self::transactionOf($connection);

        if ($transaction === null) {
            $callback();

            return;
        }

        $transaction->addCallback($callback);
    }

    public static function once(string $identity, Connection $connection, Closure $callback): void
    {
        $transaction = self::transactionOf($connection);

        if ($transaction === null) {
            $callback();

            return;
        }

        $pending = self::$pending ??= new WeakMap;
        $identities = $pending[$transaction] ?? [];

        if (array_key_exists($identity, $identities)) {
            return;
        }

        $pending[$transaction] = [...$identities, $identity => true];
        $transaction->addCallback($callback);
    }

    private static function transactionOf(Connection $connection): ?DatabaseTransactionRecord
    {
        $container = Container::getInstance();

        if (! $container->bound('db.transactions')) {
            return null;
        }

        $transaction = $container
            ->make('db.transactions')
            ->callbackApplicableTransactions()
            ->last(fn (DatabaseTransactionRecord $transaction): bool => $transaction->connection === $connection->getName());

        return $transaction instanceof DatabaseTransactionRecord ? $transaction : null;
    }
}
