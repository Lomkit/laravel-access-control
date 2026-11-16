<?php

namespace Lomkit\Access\Tests\Fixtures\Discovery\Models;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\HasControl;

class Memo extends Model
{
    use HasControl;
}
