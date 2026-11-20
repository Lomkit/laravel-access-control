<?php

namespace Lomkit\Access\Tests\Fixtures\Index;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
#[ControlledBy(GuideControl::class)]
class Guide extends Model
{
    use HasControl;
}
