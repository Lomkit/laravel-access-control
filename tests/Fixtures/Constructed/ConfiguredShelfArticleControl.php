<?php

namespace Lomkit\Access\Tests\Fixtures\Constructed;

use Illuminate\Support\Facades\Config;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;

class ConfiguredShelfArticleControl extends ArticleControl
{
    #[\Override]
    protected function perimeters(): array
    {
        return [
            ShelvedArticlesPerimeter::reading(Config::boolean('articles.featured') ? Shelf::Featured : Shelf::Plain),
        ];
    }
}
