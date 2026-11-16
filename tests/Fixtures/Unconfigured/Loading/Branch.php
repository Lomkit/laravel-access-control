<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Loading;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Search\ControlledSearchable;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Country;

#[Unguarded]
#[ControlledBy(BranchControl::class)]
class Branch extends Model
{
    use ControlledSearchable;

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Country, $this> */
    public function ministry(): BelongsTo
    {
        throw new LogicException('The ministry relation is not wired yet.');
    }

    /** @return array{id: string} */
    public function toSearchableArray(): array
    {
        return ['id' => (string) $this->getKey()];
    }
}
