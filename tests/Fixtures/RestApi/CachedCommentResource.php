<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

class CachedCommentResource extends CommentResource
{
    #[\Override]
    public function alwaysSelect(): array
    {
        return [...[], 'updated_at'];
    }

    #[\Override]
    public function authorizationCache(): array
    {
        return ['view' => 300, 'update' => 300];
    }
}
