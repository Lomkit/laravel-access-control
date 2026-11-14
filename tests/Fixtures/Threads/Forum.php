<?php

namespace Lomkit\Access\Tests\Fixtures\Threads;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Tests\Fixtures\Blog\Client;

#[Unguarded]
class Forum extends Model
{
    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<Thread, $this> */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class);
    }
}
