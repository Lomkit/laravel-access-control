<?php

namespace Lomkit\Access\Tests\Fixtures\SearchGuard;

use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;

class GuardedArticleControl extends ArticleControl
{
    protected string $model = GuardedArticle::class;
}
