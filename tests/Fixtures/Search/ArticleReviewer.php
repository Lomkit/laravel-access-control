<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Lomkit\Access\Search\ReindexesSearch;

class ArticleReviewer extends Pivot
{
    use ReindexesSearch;

    public $timestamps = false;

    protected $table = 'article_reviewers';
}
