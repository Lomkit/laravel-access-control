<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\HasControl;

class Note extends Model
{
    use HasControl;
}
