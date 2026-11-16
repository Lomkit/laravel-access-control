<?php

namespace Lomkit\Access\Tests\Fixtures\Check\DraftsGranted\Models;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\HasControl;

class Note extends Model
{
    use HasControl;
}
