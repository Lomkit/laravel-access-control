<?php

namespace Lomkit\Access\Console\Check\Problems;

/** @internal */
interface Problem
{
    public function kind(): ProblemKind;

    public function subject(): string;

    public function message(): string;
}
