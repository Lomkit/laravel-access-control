<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Models;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Controls\NoteControl;

#[ControlledBy(NoteControl::class)]
class MislinkedNote extends Model
{
    use HasControl;
}
