<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi\Search;

use Lomkit\Access\Tests\Fixtures\Threads\Thread;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class ThreadResource extends Resource
{
    public static string $model = Thread::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title'), Integer::make('author_id'), Integer::make('forum_id'), Integer::make('workspace_id')];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()->withoutPerimeter();
    }
}
