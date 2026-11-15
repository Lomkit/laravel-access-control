<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Models;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;

class Document extends Model
{
    use ControlledSearchable;
    use HasControl;
}
