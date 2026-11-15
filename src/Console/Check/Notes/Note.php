<?php

namespace Lomkit\Access\Console\Check\Notes;

/** @internal */
interface Note
{
    public function kind(): NoteKind;

    public function subject(): string;

    public function message(): string;
}
