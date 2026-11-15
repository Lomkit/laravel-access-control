<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi\Search;

use Lomkit\Rest\Http\Controllers\Controller;

class ArticleController extends Controller
{
    public static string $resource = ArticleResource::class;
}
