<?php

namespace Lomkit\Access\Tests\Fixtures\Threads;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;

#[Unguarded]
class Thread extends Model
{
    use ControlledSearchable;
    use HasControl;
    use SoftDeletes;

    /** @return BelongsTo<Forum, $this> */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class);
    }

    /** @return HasMany<Reply, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(Reply::class);
    }

    /** @return array{id: string, title: mixed} */
    public function toSearchableArray(): array
    {
        return ['id' => (string) $this->getKey(), 'title' => $this->getAttribute('title')];
    }
}
