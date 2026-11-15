<?php

namespace Lomkit\Access\Tests\Fixtures;

use Illuminate\Support\Facades\Config;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\WrittenArticlesPerimeter;

class ConfiguredVariantArticleControl extends ArticleControl
{
    #[\Override]
    protected function perimeters(): array
    {
        return [
            WrittenArticlesPerimeter::new()
                ->featured(Config::boolean('articles.featured'))
                ->allowed(fn (User $user): bool => true),
        ];
    }
}
