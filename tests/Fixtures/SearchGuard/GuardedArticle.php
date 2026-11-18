<?php

namespace Lomkit\Access\Tests\Fixtures\SearchGuard;

use Illuminate\Database\Eloquent\Attributes\Table;
use Lomkit\Access\Tests\Fixtures\Search\Article;

#[Table('articles')]
class GuardedArticle extends Article {}
