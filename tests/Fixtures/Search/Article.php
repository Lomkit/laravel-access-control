<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lomkit\Access\Attributes\ControlledBy;
use Lomkit\Access\Controls\HasControl;
use Lomkit\Access\Search\ControlledSearchable;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;

/** @property int $id */
#[Unguarded]
#[UseFactory(ArticleFactory::class)]
#[ControlledBy(ArticleControl::class)]
class Article extends Model
{
    use ControlledSearchable;
    use HasControl;

    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    #[\Override]
    protected function casts(): array
    {
        return ['featured' => 'boolean'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_shares');
    }

    /** @return BelongsTo<Folder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /** @return BelongsTo<ArchiveFolder, $this> */
    public function archiveFolder(): BelongsTo
    {
        return $this->belongsTo(ArchiveFolder::class, 'folder_id');
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function activeAssignments(): HasMany
    {
        return $this->hasMany(Assignment::class)
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $end) => $end->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    /** @return BelongsToMany<User, $this, ArticleReviewer> */
    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_reviewers')->using(ArticleReviewer::class)->withPivot('role');
    }

    /** @return BelongsToMany<Notice, $this, ArticleNotice> */
    public function notices(): BelongsToMany
    {
        return $this->belongsToMany(Notice::class, 'article_notice')->using(ArticleNotice::class);
    }

    /** @return array<array-key, mixed> */
    public function typesenseSearchParameters(): array
    {
        $parameters = config('testing.article.typesense-search-parameters', []);

        return is_array($parameters) ? $parameters : [];
    }

    /** @return array{id: string, author_id: mixed, title: mixed, featured: mixed} */
    public function toSearchableArray(): array
    {
        return [
            'id' => (string) $this->id,
            'author_id' => $this->getAttribute('author_id'),
            'title' => $this->getAttribute('title'),
            'featured' => $this->getAttribute('featured'),
        ];
    }
}
