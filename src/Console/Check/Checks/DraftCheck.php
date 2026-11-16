<?php

namespace Lomkit\Access\Console\Check\Checks;

use Lomkit\Access\Ability;
use Lomkit\Access\Console\Check\Findings;
use Lomkit\Access\Console\Check\Notes\BoundaryRefusingDrafts;
use Lomkit\Access\Console\Check\Notes\UngrantableDrafts;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Perimeters\ThroughParent;

/** @internal */
final class DraftCheck
{
    /** @param  list<Control>  $controls */
    public function run(Findings $findings, array $controls): void
    {
        foreach ($controls as $control) {
            $boundary = $control->declaration()->boundary();

            if (! $boundary->checksInMemory()) {
                $findings->note(new BoundaryRefusingDrafts($control::class, $boundary::class));
            }

            if (! $this->grantsDrafts($control)) {
                $findings->note(new UngrantableDrafts($control::class, $control->getModel()));
            }
        }
    }

    private function grantsDrafts(Control $control): bool
    {
        foreach ($control->declaration()->perimeters()->entries() as $entry) {
            foreach ($entry instanceof Levels ? $entry->all() : [$entry] as $perimeter) {
                if ($this->grantsDraft($perimeter)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function grantsDraft(Perimeter $perimeter): bool
    {
        if ($perimeter instanceof ThroughParent) {
            return $perimeter->delegation()->parentAbilityFor(Ability::create()) !== null;
        }

        return $perimeter->checksInMemory();
    }
}
