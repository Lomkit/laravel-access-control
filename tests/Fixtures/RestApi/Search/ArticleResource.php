<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi\Search;

use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

#[WithoutPerimeter]
class ArticleResource extends Resource
{
    public static string $model = Article::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title'), Integer::make('author_id')];
    }

    #[\Override]
    public function text(): ?TextSearch
    {
        return ScoutEngine::make()->withoutPerimeter();
    }
}
