<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi\Search;

use Lomkit\Access\Tests\Fixtures\Search\Snippet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

class SnippetResource extends Resource
{
    public static string $model = Snippet::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title')];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()->withoutPerimeter();
    }
}
