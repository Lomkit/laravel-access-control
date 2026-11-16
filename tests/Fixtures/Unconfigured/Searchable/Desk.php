<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Searchable;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Country;

#[Unguarded]
class Desk extends Model
{
    use ControlledSearchable;
    use HasControl;

    protected $connection = 'unconfigured';

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return array{id: string, name: mixed} */
    public function toSearchableArray(): array
    {
        return ['id' => (string) $this->getKey(), 'name' => $this->getAttribute('name')];
    }
}
