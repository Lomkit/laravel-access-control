<?php

namespace Lomkit\Access\Search\Index;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\InvalidSearchRowException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\PerimeterWithoutSearchKeysException;
use Lomkit\Access\Exceptions\SharedMorphClassException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Exceptions\UnsearchableThroughParentException;
use Lomkit\Access\Perimeters\PerimeterPositions;
use Lomkit\Access\Search\KeylessPerimeters;
use Lomkit\Access\Search\ReaderSearchKeys;
use Lomkit\Access\Search\SearchDocuments;

/** @internal */
final readonly class IndexKeys
{
    private function __construct(
        private Control $control,
        private IndexScope $scope,
    ) {}

    /**
     * @param  iterable<mixed>  $rows
     * @return array<int|string, array<string, list<string>>>
     *
     * @throws InvalidSearchRowException When a row is not an Eloquent model, is unsaved, or is not of the model of the first row.
     * @throws MissingControlException When no control is registered for the model of the rows.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     * @throws PerimeterWithoutSearchKeysException When a perimeter of the control, or of the control of a parent it reads through, declares no search keys.
     * @throws UnsearchableThroughParentException When a ThroughParent delegates view to another ability, or its parent has a boundary without search form.
     * @throws SharedMorphClassException When the morph class of the model names another model.
     */
    public static function documentsOf(iterable $rows): array
    {
        $rows = IndexRows::of($rows);
        $first = $rows->first();

        if ($first === null) {
            return [];
        }

        return self::for($first::class)->documents($rows);
    }

    /**
     * @param  class-string<Model>  $model
     *
     * @throws MissingControlException When no control is registered for the model.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     * @throws PerimeterWithoutSearchKeysException When a perimeter of the control, or of the control of a parent it reads through, declares no search keys.
     * @throws UnsearchableThroughParentException When a ThroughParent delegates view to another ability, or its parent has a boundary without search form.
     * @throws SharedMorphClassException When the morph class of the model names another model.
     */
    public static function readerKeysFor(string $model, ?Model $reader): ReaderSearchKeys
    {
        return self::for($model)->reader($reader);
    }

    /**
     * @param  class-string<Model>  $model
     *
     * @throws MissingControlException When no control is registered for the model.
     * @throws UnsearchableBoundaryException When the boundary of the control has no search form.
     * @throws PerimeterWithoutSearchKeysException When a perimeter of the control, or of the control of a parent it reads through, declares no search keys.
     * @throws UnsearchableThroughParentException When a ThroughParent delegates view to another ability, or its parent has a boundary without search form.
     * @throws SharedMorphClassException When the morph class of the model names another model.
     */
    private static function for(string $model): self
    {
        $control = Access::controlFor($model);

        if (! $control->searchRules()->isSearchable()) {
            throw UnsearchableBoundaryException::for($control);
        }

        foreach (KeylessPerimeters::in(PerimeterPositions::of($control)) as $position => $perimeter) {
            throw PerimeterWithoutSearchKeysException::forIndex($control, $position, $perimeter);
        }

        foreach ($control->searchRules()->parents() as $parent) {
            $parent->ensureIndexable($control::class);
        }

        return new self($control, IndexScope::of($control->getModel()));
    }

    /** @return array<int|string, array<string, list<string>>> */
    private function documents(IndexRows $rows): array
    {
        $rules = $this->control->searchRules();
        $readable = (new SearchDocuments($rules))->readableKeysFor($rows->all());
        $documents = [];

        foreach ($rows->keys() as $index => $key) {
            $documents[$key] = $this->scope->document($readable[$index], $rules->isBounded());
        }

        return $documents;
    }

    private function reader(?Model $reader): ReaderSearchKeys
    {
        $filter = $this->control->searchFilter($reader);
        $model = $this->control->getModel();

        if ($filter->isClosed()) {
            return ReaderSearchKeys::closed($model);
        }

        return ReaderSearchKeys::open($model, $this->scope->reader($filter->readableAccessKeys(), $filter->readableBoundaryKeys()));
    }
}
