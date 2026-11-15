<?php

namespace Lomkit\Access\Tests\Fixtures\Threads;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;

#[Unguarded]
class Reply extends Model
{
    use ControlledSearchable;
    use HasControl;

    /** @return BelongsTo<Thread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class);
    }

    /** @return array{id: string, body: mixed} */
    public function toSearchableArray(): array
    {
        return ['id' => (string) $this->getKey(), 'body' => $this->getAttribute('body')];
    }
}
