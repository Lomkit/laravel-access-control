<?php

namespace Lomkit\Access\Tests\Fixtures\Inheritance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Attributes\ControlledBy;

#[ControlledBy(BookControl::class)]
class Book extends Model
{
    public function coverChapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class, 'cover_chapter_id');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(Chapter::class, 'draft_book_id');
    }
}
