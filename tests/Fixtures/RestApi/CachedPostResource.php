<?php

namespace Lomkit\Access\Tests\Fixtures\RestApi;

class CachedPostResource extends PostResource
{
    #[\Override]
    public function alwaysSelect(): array
    {
        return [...parent::alwaysSelect(), 'updated_at'];
    }

    #[\Override]
    public function authorizationCache(): array
    {
        return ['view' => 300, 'update' => 300];
    }
}
