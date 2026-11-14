<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Models;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Invalid\ModellessControl;

#[ControlledBy(ModellessControl::class)]
class ModellessNote extends Model
{
    use HasControl;
}
