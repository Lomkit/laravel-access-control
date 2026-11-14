<?php

namespace Lomkit\Access\Testing;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
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
        $instance = Access::control($control);
        $model = $instance->getModel();

        Assert::assertContains(ControlledSearchable::class, class_uses_recursive($model), sprintf('The [%s] model does not use the ControlledSearchable trait, so its documents carry no access keys.', $model));
        Assert::assertTrue($instance->searchRules()->isSearchable(), sprintf('The boundary of the [%s] control has no search form, so a controlled search cannot keep the rows inside it.', $control));
        Assert::assertNotSame([], $readers, sprintf('assertSearchAgrees() received no reader for the [%s] control: the search agrees with the SQL only for the readers it compares.', $control));

        $agreement = new SearchAgreement($instance);

        foreach ($readers as $reader) {
            $disagreements = $agreement->disagreementsFor($reader);

            Assert::assertSame(['searchOnly' => [], 'sqlOnly' => []], $disagreements, $this->searchAgreesFailure($control, $reader, $disagreements));
        }
    }

    /**
     * @param  class-string<Control>  $control
     * @param  list<string>  $disagreements
     */
    private function controlAgreesFailure(string $control, Model $reader, array $disagreements): string
    {
        $key = $reader->getKey();

        return sprintf(
            "The decisions of the [%s] control disagree for the [%s] reader with key [%s]:\n- %s\nThe Gate must give the same answer row by row, after Access::preload() and after withAbilities(), and grant view exactly to the rows controlled() lists. A perimeter whose should callback or includes() hook describes other rows than its query() is the usual cause: make them describe the same rows, or drop the should callback so the row is checked through the query.",
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
    private function searchAgreesFailure(string $control, Model $reader, array $disagreements): string
    {
        $key = $reader->getKey();

        return sprintf(
            'The search keys of the [%s] control do not agree with its SQL for the [%s] reader with key [%s]: the keys find the rows [%s] that the SQL does not, and miss the rows [%s] that the SQL finds. Make the searchKeys() of each perimeter, and of the boundary, describe the same rows as its query().',
            $control,
            $reader::class,
            is_int($key) || is_string($key) ? $key : get_debug_type($key),
            implode(', ', $disagreements['searchOnly']),
            implode(', ', $disagreements['sqlOnly']),
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
