<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Access\Attributes\ControlledBy;

#[Table('pages')]
#[ControlledBy(ArchivedNoticeControl::class)]
class ArchivedNotice extends Notice
{
    use SoftDeletes;
}
