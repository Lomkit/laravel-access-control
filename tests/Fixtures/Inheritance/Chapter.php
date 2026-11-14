<?php

namespace Lomkit\Access\Tests\Fixtures\Inheritance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Lomkit\Access\Attributes\ControlledBy;

#[ControlledBy(ChapterControl::class)]
class Chapter extends Model
{
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_id');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(self::class, 'previous_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function title(): string
    {
        return 'Chapter';
    }
}
