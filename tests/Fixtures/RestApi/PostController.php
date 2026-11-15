<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

use Lomkit\Rest\Http\Controllers\Controller;

class PostController extends Controller
{
    public static string $resource = PostResource::class;
}
