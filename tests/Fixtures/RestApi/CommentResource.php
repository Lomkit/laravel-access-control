<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Rest\Attributes\WithoutPerimeter;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;

#[WithoutPerimeter]
class CommentResource extends Resource
{
    public static string $model = Comment::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('body'), Integer::make('post_id'), Integer::make('author_id')];
    }

    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('post', PostResource::class)->withoutOperations(MutationOperation::Detach)];
    }
}
