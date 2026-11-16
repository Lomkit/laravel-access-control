<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Searchable\Desk;

#[Unguarded]
class Country extends Model
{
    use HasControl;

    protected $connection = 'unconfigured';

    /** @return HasMany<Office, $this> */
    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }

    /** @return HasMany<Desk, $this> */
    public function desks(): HasMany
    {
        return $this->hasMany(Desk::class);
    }

    /** @return HasMany<Office, $this> */
    public function headquarters(): HasMany
    {
        return $this->hasMany(Office::class, 'headquarters_id');
    }
}
