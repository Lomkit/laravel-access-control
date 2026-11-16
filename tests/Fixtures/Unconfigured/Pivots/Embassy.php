<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Pivots;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Country;

class Embassy extends Model
{
    protected $connection = 'unconfigured';

    /** @return BelongsToMany<Country, $this, EmbassyCountry> */
    public function hosts(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'embassy_country')->using(EmbassyCountry::class);
    }

    /** @return BelongsToMany<Country, $this> */
    public function allies(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'embassy_country');
    }
}
