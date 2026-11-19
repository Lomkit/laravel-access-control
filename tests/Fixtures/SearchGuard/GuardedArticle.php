<?php

namespace Lomkit\Access\Tests\Fixtures\SearchGuard;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lomkit\Access\Tests\Fixtures\Search\Article;

#[Table('articles')]
class GuardedArticle extends Article
{
    use SoftDeletes;
}
