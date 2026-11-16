<?php

namespace Lomkit\Access\Tests\Fixtures\Unconfigured\Broken;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Country;

class Stall extends Model
{
    use ControlledSearchable;
    use HasControl;

    protected $connection = 'unconfigured';

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        throw new LogicException('The country relation of the stall is not wired yet.');
    }

    /** @return array{id: string} */
    public function toSearchableArray(): array
    {
        return ['id' => (string) $this->getKey()];
    }
}
