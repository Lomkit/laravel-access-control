<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\ModelObserver;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Search\SearchDocuments;
use Lomkit\Access\Search\SearchEngineKind;
use Lomkit\Access\Search\SearchField;

/** @internal */
final class KeyDrift
{
    /** @throws MissingControlledSearchableException When the model is not searchable. */
    public static function follow(Model $model): void
    {
        if (! method_exists($model, 'searchIndexShouldBeUpdated') || ! method_exists($model, 'wasSearchableBeforeUpdate')) {
            throw MissingControlledSearchableException::forReindex($model::class);
        }

        if ($model->searchIndexShouldBeUpdated() === true || ModelObserver::syncingDisabledFor($model)) {
            return;
        }

        $current = ModelSnapshot::current($model);
        $previous = ModelSnapshot::previous($model);
        $wasSearchable = $model->wasSearchableBeforeUpdate() === true;

        AfterCommit::run($model->getConnection(), static function () use ($model, $current, $previous, $wasSearchable): void {
            if (self::drifted($model, $previous, $current)) {
                self::reindex($model, $wasSearchable);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $current
     *
     * @throws MissingControlledSearchableException When the model is not searchable.
     */
    private static function drifted(Model $model, array $previous, array $current): bool
    {
        if (! method_exists($model, 'searchableUsing')) {
            throw MissingControlledSearchableException::forReindex($model::class);
        }

        if (! SearchEngineKind::storesDocuments($model->searchableUsing())) {
            return false;
        }

        $documents = (new SearchDocuments(Access::controlFor($model)->searchRules()))
            ->readableKeysFor([$model->newFromBuilder($previous), $model->newFromBuilder($current)]);

        foreach ([SearchField::Access, SearchField::Boundary] as $field) {
            if ($documents[0][$field->value] !== $documents[1][$field->value]) {
                return true;
            }
        }

        return false;
    }

    /** @throws MissingControlledSearchableException When the model is not searchable. */
    private static function reindex(Model $model, bool $wasSearchable): void
    {
        if (! method_exists($model, 'shouldBeSearchable') || ! method_exists($model, 'searchable') || ! method_exists($model, 'unsearchable')) {
            throw MissingControlledSearchableException::forReindex($model::class);
        }

        if ($model->shouldBeSearchable() === true) {
            $model->searchable();
        } elseif ($wasSearchable) {
            $model->unsearchable();
        }
    }
}
