<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder as ScoutBuilder;
use Lomkit\Access\Controls\Control;

class ArticleControl extends Control
{
    protected string $model = Article::class;

    #[\Override]
    protected function perimeters(): array
    {
        return [
            FeaturedPerimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $user->can("{$method} featured articles"))
                ->should(fn (Model $user, Article $article): bool => $article->featured)
                ->query(function (Builder $query, Model $user): void {
                    $query->where('featured', true);
                })
                ->scoutQuery(function (ScoutBuilder $query, Model $user): void {
                    $query->where('featured', true);
                }),
            GlobalPerimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $user->can("{$method} global articles"))
                ->unrestricted(),
            AuthorPerimeter::new()
                ->allowed(fn (Model $user, string $method): bool => $user->can("{$method} own articles"))
                ->should(fn (Model $user, Article $article): bool => $article->author_id === $user->getKey())
                ->query(fn (Builder $query, Model $user): Builder => $query->where('author_id', $user->getKey()))
                ->scoutQuery(fn (ScoutBuilder $query, Model $user): ScoutBuilder => $query->where('author_id', $user->getKey())),
        ];
    }
}
