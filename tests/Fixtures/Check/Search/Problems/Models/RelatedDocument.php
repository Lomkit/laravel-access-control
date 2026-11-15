<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;

class RelatedDocument extends Model
{
    use ControlledSearchable;
    use HasControl;

    /** @return BelongsTo<Folder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}
