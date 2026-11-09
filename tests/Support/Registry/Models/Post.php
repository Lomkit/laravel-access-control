<?php

namespace Lomkit\Access\Tests\Support\Registry\Models;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Controls\HasControl;

class Post extends Model
{
    use HasControl;
}
