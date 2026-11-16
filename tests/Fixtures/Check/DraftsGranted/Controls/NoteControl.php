<?php

namespace Lomkit\Access\Tests\Fixtures\Check\DraftsGranted\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Check\DraftsGranted\AuthoredPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\DraftsGranted\Models\Note;

class NoteControl extends Control
{
    protected string $model = Note::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [AuthoredPerimeter::new()];
    }
}
