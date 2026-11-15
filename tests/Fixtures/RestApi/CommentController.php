<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

use Lomkit\Rest\Http\Controllers\Controller;

class CommentController extends Controller
{
    public static string $resource = CommentResource::class;
}
