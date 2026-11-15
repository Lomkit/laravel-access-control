<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\HasMany;

#[WithoutPerimeter]
class PostResource extends Resource
{
    public static string $model = Post::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('title'), Integer::make('project_id'), Integer::make('author_id'), Integer::make('workspace_id')];
    }

    #[\Override]
    public function alwaysSelect(): array
    {
        return ['archived_at'];
    }

    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('comments', CommentResource::class)->withoutOperations(MutationOperation::Detach)];
    }

    #[\Override]
    public function metrics(): array
    {
        return [PostCount::class];
    }

    #[\Override]
    public function gates(): array
    {
        return ['approve'];
    }
}
