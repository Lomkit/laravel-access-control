<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
interface PerimeterProblem extends Problem
{
    public function position(): string;
}
