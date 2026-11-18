<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
interface PositionedProblem extends Problem
{
    public function position(): ?string;
}
