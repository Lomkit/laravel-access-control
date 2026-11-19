<?php

namespace Lomkit\Access\Tests\Fixtures\Links;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
#[ControlledBy(MemoControl::class)]
class Memo extends Model
{
    use HasControl;

    public function links(): HasMany
    {
        return $this->hasMany(MemoLink::class);
    }
}
