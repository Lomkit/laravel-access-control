<?php

namespace Lomkit\Access\Tests\Fixtures\Agencies;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Unguarded]
#[UseFactory(AgencyFactory::class)]
class Agency extends Model
{
    use HasFactory;

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
