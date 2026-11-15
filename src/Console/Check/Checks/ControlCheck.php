<?php

namespace Lomkit\Access\Console\Check\Checks;

use Lomkit\Access\Console\Check\ControlledModels;
use Lomkit\Access\Console\Check\Findings;
use Lomkit\Access\Console\Check\Problems\ControlFailure;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Registry\ControlRegistry;
use Throwable;

/** @internal */
final readonly class ControlCheck
{
    public function __construct(private ControlRegistry $registry) {}

    /** @return list<Control> */
    public function run(Findings $findings, ControlledModels $models): array
    {
        $built = [];

        foreach ($models->all() as $class) {
            try {
                $control = $this->registry->control($class);
                $control->verifyPerimeters();
                $built[] = $control;
            } catch (Throwable $exception) {
                $findings->problem(new ControlFailure($class, $exception->getMessage()));
            }
        }

        return $built;
    }
}
