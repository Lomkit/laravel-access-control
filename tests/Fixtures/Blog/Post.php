<?php

namespace Lomkit\Access\Tests\Fixtures\Blog;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;

#[Unguarded]
#[UseFactory(PostFactory::class)]
#[UsePolicy(PostPolicy::class)]
#[ControlledBy(PostControl::class)]
class Post extends Model
{
    use HasControl;
    use HasFactory;

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('published', true);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_shares');
    }
}
