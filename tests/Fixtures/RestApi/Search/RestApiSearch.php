<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi\Search;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;

class RestApiSearch
{
    public static function routes(): void
    {
        Route::middleware('auth')->group(function (): void {
            Rest::resource('threads', ThreadController::class);
            Rest::resource('replies', ReplyController::class);
            Rest::resource('articles', ArticleController::class);
        });
    }

    public static function snippetRoutes(): void
    {
        Route::middleware('auth')->group(function (): void {
            Rest::resource('snippets', SnippetController::class);
        });
    }
}
