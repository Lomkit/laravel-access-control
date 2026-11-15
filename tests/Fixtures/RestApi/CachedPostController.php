<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

use Lomkit\Rest\Http\Controllers\Controller;

class CachedPostController extends Controller
{
    public static string $resource = CachedPostResource::class;
}
