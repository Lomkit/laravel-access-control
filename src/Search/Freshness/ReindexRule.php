<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\InvalidReindexRowsException;

/** @internal */
interface ReindexRule
{
    /** @return class-string<Model> */
    public function model(): string;

    public function watches(Model $instance): bool;

    public function isTriggeredBy(WatchedEvent $event, Model $instance): bool;

    /** @return array<string, mixed> */
    public function previousValues(Model $instance): array;

    /**
     * @param  class-string<Model>  $controlled
     * @return Builder<Model>
     *
     * @throws InvalidReindexRowsException When the rows are no query of the controlled model.
     */
    public function rowsFor(Model $instance, string $keys, string $controlled): Builder;
}
