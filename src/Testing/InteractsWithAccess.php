<?php

namespace Lomkit\Access\Testing;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
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
