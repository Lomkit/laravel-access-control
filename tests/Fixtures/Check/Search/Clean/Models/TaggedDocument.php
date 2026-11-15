<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;

class TaggedDocument extends Model
{
    use ControlledSearchable;
    use HasControl;

    /** @return BelongsToMany<Tag, $this, DocumentTag> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'document_tag')->using(DocumentTag::class);
    }
}
