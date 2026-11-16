<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $connection = 'unconfigured';
}
