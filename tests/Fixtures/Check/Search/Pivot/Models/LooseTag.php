<?php

namespace Lomkit\Access\Tests\Fixtures\Check\Search\Pivot\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Models\TaggedDocument;

class LooseTag extends Model
{
    /** @return BelongsToMany<TaggedDocument, $this> */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(TaggedDocument::class, 'document_tag');
    }
}
