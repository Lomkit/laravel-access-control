<?php

namespace Lomkit\Access\Tests\Fixtures\Types;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;

final class PostRows
{
    /** @param  Builder<covariant Model>  $query */
    public static function visible(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  Builder<covariant Model>  $query
     * @return Builder<TModel>
     *
     * @throws RowsOfAnotherModelException When the query reads the rows of another model.
     */
    public static function of(string $model, Builder $query): Builder
    {
        if (! $query->getModel() instanceof $model) {
            throw RowsOfAnotherModelException::for($model, $query->getModel());
        }

        /** @var Builder<TModel> $query */
        return $query;
    }

    /** @param  Builder<Post>  $query */
    public static function publishedBy(Builder $query, User $author): void
    {
        $query->published()->whereBelongsTo($author, 'author');
    }
}
