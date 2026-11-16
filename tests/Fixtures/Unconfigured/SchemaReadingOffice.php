<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemaReadingOffice extends Model
{
    protected $connection = 'unconfigured';

    protected $table = 'offices';

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        $foreignKey = $this->getConnection()->getSchemaBuilder()->hasColumn('offices', 'nation_id') ? 'nation_id' : 'country_id';

        return $this->belongsTo(Country::class, $foreignKey);
    }
}
