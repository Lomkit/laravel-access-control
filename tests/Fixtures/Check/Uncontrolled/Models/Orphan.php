<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Uncontrolled\Models;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\HasControl;

class Orphan extends Model
{
    use HasControl;
}
