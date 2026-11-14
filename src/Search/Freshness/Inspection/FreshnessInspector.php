<?php

namespace Lomkit\Access\Search\Freshness\Inspection;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Search\ControlledSearchable;
use Lomkit\Access\Search\Freshness\ScoutOptions;
use Lomkit\Access\Search\UsesControlledSearchable;

/** @internal */
final class FreshnessInspector
{
    private const string INDEX_CHECK = 'searchIndexShouldBeUpdated';

    private const string MAKE_SEARCHABLE = 'makeSearchableUsing';

    private const string WITH_ACCESS_KEYS = 'withAccessKeys';

    /**
     * @param  class-string<Model>  $model
     *
     * @throws MissingControlledSearchableException When the model does not use ControlledSearchable.
     * @throws MissingControlException When no control is registered for the model.
     */
    public static function inspect(string $model): FreshnessReport
    {
        if (! UsesControlledSearchable::check($model)) {
            throw MissingControlledSearchableException::forReindex($model);
        }

        $issues = [];

        if (! ScoutOptions::afterCommit()) {
            $issues[] = new ScoutIndexesBeforeCommit($model);
        }

        if (TraitMethod::isOverridden($model, self::INDEX_CHECK, Searchable::class)) {
            $issues[] = new IndexCheckOverridden($model);
        }

        if (TraitMethod::isOverridden($model, self::MAKE_SEARCHABLE, ControlledSearchable::class) && ! TraitMethod::mentions($model, self::MAKE_SEARCHABLE, self::WITH_ACCESS_KEYS)) {
            $issues[] = new AccessKeysNotWritten($model);
        }

        $rules = Access::controlFor($model)->searchRules();

        foreach ([...$rules->keys(), ...array_filter([$rules->boundaryKeys()])] as $keys) {
            array_push($issues, ...KeyRelations::unwatched($model, $keys, $rules->reindexes()));
        }

        return new FreshnessReport($model, $issues);
    }
}
