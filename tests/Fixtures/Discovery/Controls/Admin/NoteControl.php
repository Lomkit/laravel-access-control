<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Controls\Admin;

use Lomkit\Access\Controls\Control;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Admin\Note;

class NoteControl extends Control
{
    protected string $model = Note::class;
}
