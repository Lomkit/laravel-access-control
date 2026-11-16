<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

#[Unguarded]
class Visit extends Model {}
