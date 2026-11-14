<?php

namespace Lomkit\Access\Tests\Fixtures\Agencies;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Unguarded]
#[UseFactory(MemberFactory::class)]
class Member extends Authenticatable
{
    use HasFactory;

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
