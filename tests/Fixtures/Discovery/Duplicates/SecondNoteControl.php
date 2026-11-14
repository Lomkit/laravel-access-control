<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Duplicates;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Note;

class SecondNoteControl extends Control
{
    protected string $model = Note::class;
}
