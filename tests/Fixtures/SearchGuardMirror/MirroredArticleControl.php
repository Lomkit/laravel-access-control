<?php

namespace Lomkit\Access\Tests\Fixtures\SearchGuardMirror;

use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;

class MirroredArticleControl extends ArticleControl
{
    protected string $model = MirroredArticle::class;
}
