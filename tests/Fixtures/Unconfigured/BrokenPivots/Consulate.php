<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\BrokenPivots;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Country;

class Consulate extends Model
{
    protected $connection = 'unconfigured';

    /** @return BelongsToMany<Country, $this> */
    public function countries(): BelongsToMany
    {
        throw new LogicException('The countries relation is not wired yet.');
    }
}
