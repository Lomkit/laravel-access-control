<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\InvalidReindexRowsException;
use Lomkit\Access\Exceptions\MissingReindexDeclarationException;
use Lomkit\Access\Registry\ControlRegistry;

/** @internal */
final class ReindexWatchedRows implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    private const int UNIQUE_FOR_SECONDS = 3600;

    /**
     * @param  class-string<Control>  $control
     * @param  class-string<Model>  $watched
     */
    public function __construct(
        public readonly string $control,
        public readonly string $keys,
        public readonly string $watched,
        public readonly WatchedChange $change,
    ) {}

    /** @param  class-string<Control>  $control */
    public static function of(string $control, string $keys, ReindexRule $reindex, Model $instance, WatchedEvent $event): self
    {
        return new self($control, $keys, $reindex->model(), WatchedChange::of($event, $reindex, $instance));
    }

    public function uniqueId(): string
    {
        return hash('sha256', serialize([$this->control, $this->keys, $this->watched, $this->change->identity()]));
    }

    public function uniqueFor(): int
    {
        return self::UNIQUE_FOR_SECONDS;
    }

    /**
     * @throws MissingReindexDeclarationException When the control no longer declares the reindexWhen() that queued the job.
     * @throws InvalidReindexRowsException When the rows callback returns no query or relation of the controlled model.
     */
    public function handle(ControlRegistry $registry): void
    {
        $control = $registry->control($this->control);
        $reindex = $control->searchRules()->freshness()->for($this->keys, $this->watched)
            ?? throw MissingReindexDeclarationException::for($this->control, $this->keys, $this->watched);

        $rows = [];

        foreach ($this->change->instancesOf(new ($this->watched)) as $instance) {
            $query = $reindex->rowsFor($instance, $this->keys, $control->getModel());
            $rows[self::identity($query)] = $query;
        }

        SearchableRows::reindex($control->getModel(), array_values($rows));
    }

    /** @param  Builder<Model>  $query */
    private static function identity(Builder $query): string
    {
        return $query->toSql()."\0".serialize($query->getBindings());
    }
}
