<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Claimed\Models;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Tests\Fixtures\Check\Claimed\Declared\ClaimedControl;

#[ControlledBy(ClaimedControl::class)]
class Claimed extends Model
{
    use HasControl;
}
