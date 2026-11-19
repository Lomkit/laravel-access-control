<?php

namespace Lomkit\Access\Tests\Fixtures\Links;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Unguarded]
class MemoLink extends Model
{
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
