<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Controls;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Note;

class NoteControl extends Control
{
    protected string $model = Note::class;
}
