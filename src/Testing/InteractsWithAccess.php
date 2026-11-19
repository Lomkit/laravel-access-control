<?php

namespace Lomkit\Access\Testing;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Exceptions\IgnoredArgumentException;
use Lomkit\Access\Exceptions\UnsearchableAbilityException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Search\ControlledSearchable;
use PHPUnit\Framework\Assert;

trait InteractsWithAccess
{
    /**
     * @param  class-string<Control>  $control
     * @param  list<Model>  $readers
     */
    public function assertLevelsNest(string $control, array $readers): void
    {
        if (func_num_args() > 2) {
            throw IgnoredArgumentException::levelsNest($control);
        }

        $groups = Access::control($control)->declaration()->perimeters()->levels();

        Assert::assertNotSame([], $groups, sprintf('The [%s] control declares no Levels group, so it has no levels to nest.', $control));
        Assert::assertNotSame([], $readers, sprintf('assertLevelsNest() received no reader for the [%s] control: the levels nest only on the rows that each reader reaches.', $control));

        foreach ($readers as $reader) {
            $nesting = new LevelNesting(Access::control($control), $reader);

            foreach ($groups as $group) {
                $this->assertGroupNests($control, $group, $reader, $nesting);
            }
        }
    }

    /**
     * @param  class-string<Control>  $control
     * @param  iterable<Model>  $rows
     * @param  list<string>  $abilities
     */
    public function assertControlAgrees(string $control, Model $reader, iterable $rows, array $abilities = ['view', 'update', 'delete']): void
    {
        $instance = Access::control($control);
        $model = $instance->getModel();
        $saved = [];

        Assert::assertContains(HasControl::class, class_uses_recursive($model), sprintf('The [%s] model does not use the HasControl trait, so assertControlAgrees() cannot compare the decisions with controlled() and withAbilities().', $model));

        foreach ($rows as $row) {
            Assert::assertInstanceOf($model, $row, sprintf('assertControlAgrees() received a row that is not a [%s] model: pass the saved rows of the [%s] control.', $model, $control));
            Assert::assertTrue($row->exists, sprintf('assertControlAgrees() received an unsaved [%s] row: a draft is judged only in memory, so pass saved rows.', $model));
            $saved[] = $row;
        }

        if ($saved === []) {
            Assert::fail(sprintf('assertControlAgrees() received no row for the [%s] control: the decisions agree only on the rows they compare.', $control));
        }

        $disagreements = (new ControlAgreement($instance, $saved, $abilities))->disagreementsFor($reader);

        Assert::assertSame([], $disagreements, $this->controlAgreesFailure($control, $reader, $disagreements));
    }

    /**
     * @param  class-string<Control>  $control
     * @param  list<Model>  $readers
     */
    public function assertSearchAgrees(string $control, array $readers): void
    {
        if (func_num_args() > 2) {
            throw UnsearchableAbilityException::agreement($control);
        }

        $instance = Access::control($control);
        $model = $instance->getModel();

        Assert::assertContains(ControlledSearchable::class, class_uses_recursive($model), sprintf('The [%s] model does not use the ControlledSearchable trait, so its documents carry no access keys.', $model));
        Assert::assertTrue($instance->searchRules()->isSearchable(), sprintf('The boundary of the [%s] control has no search form, so a controlled search cannot keep the rows inside it.', $control));
        Assert::assertNotSame([], $readers, sprintf('assertSearchAgrees() received no reader for the [%s] control: the search agrees with the SQL only for the readers it compares.', $control));

        $agreement = new SearchAgreement($instance);
        $agreed = ['searchOnly' => [], 'sqlOnly' => []];

        foreach ($readers as $reader) {
            $disagreements = $agreement->disagreementsFor($reader);
            $failure = $disagreements === $agreed ? '' : $this->searchAgreesFailure($control, $reader, $agreement->readerKeys($reader), $disagreements);

            Assert::assertSame($agreed, $disagreements, $failure);
        }
    }

    /**
     * @param  class-string<Control>  $control
     * @param  iterable<Model>  $rows
     * @param  Closure(int|string, Closure(): void): mixed  $alone
     * @param  list<string>  $abilities
     */
    public function assertRowContextAgrees(string $control, Model $reader, iterable $rows, Closure $alone, array $abilities = ['view', 'update', 'delete']): void
    {
        $instance = Access::control($control);
        $columns = array_values(array_unique(array_filter(array_map(fn (ThroughParent $parent): ?string => $parent->rowContextColumn(), $instance->declaration()->perimeters()->parents()))));
        $model = $instance->getModel();

        Assert::assertContains(HasControl::class, class_uses_recursive($model), sprintf('The [%s] model does not use the HasControl trait, so assertRowContextAgrees() cannot compare the lists.', $model));
        Assert::assertCount(1, $columns, sprintf('assertRowContextAgrees() needs exactly one column decidedInRowContext() on the ThroughParent perimeters of the [%s] control, found [%s].', $control, implode(', ', $columns)));

        $saved = [];

        foreach ($rows as $row) {
            Assert::assertTrue($row instanceof $model && $row->exists, sprintf('assertRowContextAgrees() received a row that is not a saved [%s] model: pass the saved rows of the [%s] control.', $model, $control));
            $saved[] = $row;
        }

        $result = (new RowContextAgreement($instance, $columns[0], $saved, $alone, $abilities))->disagreementsFor($reader);
        $key = $reader->getKey();

        Assert::assertGreaterThan(0, $result['compared'], sprintf('assertRowContextAgrees() received no saved [%s] row whose [%s] is one of the values of the request: it compares only the rows of the values of the request.', $model, $columns[0]));
        Assert::assertSame([], $result['disagreements'], sprintf(
            "The decisions of the [%s] control under the request disagree, for the [%s] reader with key [%s], with the decisions in a request of the value of each row alone, as the alone closure sets it up:\n- %s\nThe usual causes are a within closure of Access::rowContextUsing() that does not switch a state the parent reads (the brands, the permission team, the tenant), and a perimeter or the boundary of the child that reads the values of the request instead of the value of the row.",
            $control,
            $reader::class,
            is_int($key) || is_string($key) ? $key : get_debug_type($key),
            implode("\n- ", $result['disagreements']),
        ));
    }

    /**
     * @param  class-string<Control>  $control
     * @param  list<string>  $disagreements
     */
    private function controlAgreesFailure(string $control, Model $reader, array $disagreements): string
    {
        $key = $reader->getKey();

        return sprintf(
            "The decisions of the [%s] control disagree for the [%s] reader with key [%s]:\n- %s\nThe Gate must give the same answer row by row, after Access::preload() and after withAbilities(), and grant each ability exactly to the rows controlled() lists for it. A perimeter whose should callback or includes() hook describes other rows than its query() is the usual cause: make them describe the same rows, or drop the should callback so the row is checked through the query. A policy method that adds its own rule to an ability also disagrees with controlled() for that ability: move the rule into the control.",
            $control,
            $reader::class,
            is_int($key) || is_string($key) ? $key : get_debug_type($key),
            implode("\n- ", $disagreements),
        );
    }

    /**
     * @param  class-string<Control>  $control
     * @param  array{searchOnly: list<string>, sqlOnly: list<string>}  $disagreements
     */
    private function searchAgreesFailure(string $control, Model $reader, string $readerKeys, array $disagreements): string
    {
        $key = $reader->getKey();

        return sprintf(
            'The search keys of the [%s] control do not agree with its SQL for the [%s] reader with key [%s], whose search holds [%s]: the keys find the rows [%s] that the SQL does not, and miss the rows [%s] that the SQL finds. Make the searchKeys() of each perimeter, and of the boundary, describe the same rows as its query().',
            $control,
            $reader::class,
            is_int($key) || is_string($key) ? $key : get_debug_type($key),
            $readerKeys,
            implode('; ', $disagreements['searchOnly']),
            implode('; ', $disagreements['sqlOnly']),
        );
    }

    /** @param  class-string<Control>  $control */
    private function assertGroupNests(string $control, Levels $group, Model $reader, LevelNesting $nesting): void
    {
        $levels = $group->all();

        for ($position = 1; $position < count($levels); $position++) {
            $upper = $levels[$position - 1];
            $lower = $levels[$position];

            Assert::assertSame([], $nesting->rowsMissingFrom($upper, $lower), $this->levelsNestFailure($control, $upper, $lower, $reader));
        }
    }

    /** @param  class-string<Control>  $control */
    private function levelsNestFailure(string $control, Perimeter $upper, Perimeter $lower, Model $reader): string
    {
        $key = $reader->getKey();

        return sprintf(
            'The levels of the [%s] control do not nest for the [%s] reader with key [%s]: rows of the [%s] level are missing from the [%s] level above it. Each level must contain the rows of the levels below it; move a perimeter that does not out of the Levels group, where it adds up with the others.',
            $control,
            $reader::class,
            is_int($key) || is_string($key) ? $key : get_debug_type($key),
            $lower::class,
            $upper::class,
        );
    }
}
