<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

use Lomkit\Rest\Http\Controllers\Controller;

class CachedCommentController extends Controller
{
    public static string $resource = CachedCommentResource::class;
}
