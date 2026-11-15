<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

class RestApi
{
    public static function routes(): void
    {
        Route::middleware('auth')->group(function (): void {
            Rest::resource('posts', PostController::class);
            Rest::resource('comments', CommentController::class);
            Rest::resource('cached-posts', CachedPostController::class);
            Rest::resource('cached-comments', CachedCommentController::class);
        });
    }
}
