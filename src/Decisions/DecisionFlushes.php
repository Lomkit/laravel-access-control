<?php

namespace Lomkit\Access\Decisions;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Foundation\Http\Events\RequestHandled;

/** @internal */
final readonly class DecisionFlushes
{
    private const array EVENTS = ['eloquent.saved: *', 'eloquent.deleted: *', TransactionRolledBack::class, RequestHandled::class];

    public function __construct(private Container $container) {}

    public function listen(Dispatcher $events): void
    {
        $events->listen(self::EVENTS, function (): void {
            $this->flush();
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
}
