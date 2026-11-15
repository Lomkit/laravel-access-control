<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;

class KeylessDocument extends Model
{
    use ControlledSearchable;
    use HasControl;
}
