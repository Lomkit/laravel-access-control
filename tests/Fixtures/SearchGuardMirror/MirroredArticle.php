<?php

namespace Lomkit\Access\Tests\Fixtures\SearchGuardMirror;

use Illuminate\Database\Eloquent\Attributes\Table;
use Lomkit\Access\Tests\Fixtures\Search\Article;

#[Table('articles')]
class MirroredArticle extends Article {}
