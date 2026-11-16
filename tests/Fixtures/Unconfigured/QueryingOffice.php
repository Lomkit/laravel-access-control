<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryingOffice extends Model
{
    protected $connection = 'unconfigured';

    protected $table = 'offices';

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class)->whereKeyNot(Country::query()->min('id'));
    }
}
