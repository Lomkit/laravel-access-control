<?php

namespace Lomkit\Access\Console\Check\Checks;

use Laravel\Scout\Engines\NullEngine;
use Laravel\Scout\Searchable;
use Lomkit\Access\Console\Check\Findings;
use Lomkit\Access\Console\Check\PerimeterPositions;
use Lomkit\Access\Console\Check\Problems\PerimeterWithoutSearchKeys;
use Lomkit\Access\Console\Check\Problems\PlainSearchable;
use Lomkit\Access\Console\Check\Problems\Problem;
use Lomkit\Access\Console\Check\Problems\SearchFreshness;
use Lomkit\Access\Console\Check\Problems\UnreadableRelation;
use Lomkit\Access\Console\Check\Problems\UnsearchableBoundary;
use Lomkit\Access\Console\Check\Problems\UnsupportedSearchEngine;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Search\Freshness\Inspection\FreshnessInspector;
use Lomkit\Access\Search\Freshness\Inspection\FreshnessIssue;
use Lomkit\Access\Search\Freshness\Inspection\KeyRelationIssue;
use Lomkit\Access\Search\Freshness\Inspection\UnreadableKeyRelation;
use Lomkit\Access\Search\KeylessPerimeters;
use Lomkit\Access\Search\SearchEngineKind;
use Lomkit\Access\Search\SearchKeys;
use Lomkit\Access\Search\UsesControlledSearchable;
use Throwable;

/** @internal */
final class SearchCheck
{
    /** @param  list<Control>  $controls */
    public function run(Findings $findings, array $controls): void
    {
        foreach ($controls as $control) {
            $model = $control->getModel();

            if (UsesControlledSearchable::check($model)) {
                $this->checkSearchable($findings, $control);
            } elseif (in_array(Searchable::class, class_uses_recursive($model), true)) {
                $findings->problem(new PlainSearchable($model, MissingControlledSearchableException::for(new $model)->getMessage()));
            }
        }
    }

    private function checkSearchable(Findings $findings, Control $control): void
    {
        $model = $control->getModel();

        try {
            $engine = ModelEngine::of($model);
            SearchEngineKind::of($engine, new $model);
        } catch (Throwable $exception) {
            $findings->problem(new UnsupportedSearchEngine($model, $exception->getMessage()));

            return;
        }

        if (! SearchEngineKind::storesDocuments($engine) || $engine instanceof NullEngine) {
            return;
        }

        if (! $control->searchRules()->isSearchable()) {
            $findings->problem(new UnsearchableBoundary($control::class, UnsearchableBoundaryException::for($control)->getMessage()));
        }

        $positions = PerimeterPositions::of($control);

        foreach (KeylessPerimeters::in($positions) as $position => $perimeter) {
            $findings->problem(new PerimeterWithoutSearchKeys($control::class, $position, $perimeter::class, $model, HeldPerimeters::in($perimeter)));
        }

        foreach (FreshnessInspector::inspect($model)->issues() as $issue) {
            $findings->problem($this->problemOf($issue, $issue instanceof KeyRelationIssue ? $this->positionOf($issue->declaredKeys(), $positions) : null));
        }
    }

    private function problemOf(FreshnessIssue $issue, ?string $position): Problem
    {
        if (! $issue instanceof UnreadableKeyRelation) {
            return new SearchFreshness($issue, $position);
        }

        return new UnreadableRelation($issue->owner(), $issue->relation(), $issue->reason(), sprintf('the [%s] search keys of the [%s] model', $issue->keys(), $issue->model()), $position);
    }

    /** @param  array<string, Perimeter>  $positions */
    private function positionOf(SearchKeys $keys, array $positions): ?string
    {
        foreach ($positions as $position => $perimeter) {
            if ($perimeter->declaredSearchKeys() === $keys) {
                return $position;
            }
        }

        return null;
    }
}
