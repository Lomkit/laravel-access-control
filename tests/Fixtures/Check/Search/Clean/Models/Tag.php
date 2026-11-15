<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    /** @return BelongsToMany<TaggedDocument, $this, DocumentTag> */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(TaggedDocument::class, 'document_tag')->using(DocumentTag::class);
    }
}
