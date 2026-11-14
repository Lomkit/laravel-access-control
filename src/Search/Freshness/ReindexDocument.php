<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/** @internal */
final class ReindexDocument implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    private const int UNIQUE_FOR_SECONDS = 3600;

    /** @param  class-string<Model>  $model */
    public function __construct(
        public readonly string $model,
        public readonly int|string $key,
    ) {}

    public function uniqueId(): string
    {
        return $this->model.':'.$this->key;
    }

    public function uniqueFor(): int
    {
        return self::UNIQUE_FOR_SECONDS;
    }

    public function handle(): void
    {
        SearchableRows::reindex($this->model, [(new ($this->model))->newQueryWithoutScopes()->whereKey($this->key)]);
    }
}
