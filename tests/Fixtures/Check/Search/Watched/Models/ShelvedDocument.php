<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Watched\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;

class ShelvedDocument extends Model
{
    use ControlledSearchable;
    use HasControl;

    /** @return BelongsTo<Shelf, $this> */
    public function shelf(): BelongsTo
    {
        return $this->belongsTo(Shelf::class);
    }
}
