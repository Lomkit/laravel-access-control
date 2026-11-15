<?php

namespace Lomkit\Access\Tests\Fixtures\Threads;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
class Board extends Model
{
    use HasControl;

    /** @return BelongsTo<Forum, $this> */
    public function forum(): BelongsTo
    {
        return $this->belongsTo(Forum::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
