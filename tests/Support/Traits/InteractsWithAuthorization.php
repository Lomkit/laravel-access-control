<?php

namespace Lomkit\Access\Tests\Support\Traits;

trait InteractsWithAuthorization
{
    protected function withAuthenticatedUser($user = null, string $driver = 'web')
    {
        return $this->actingAs($user ?? $this->resolveAuthFactoryClass()::new()->create(), $driver);
    }

    protected function resolveAuthFactoryClass()
    {
        return null;
    }

    protected function assertUnauthorizedResponse($response)
    {
        $response->assertStatus(403);
        $response->assertJson(['message' => 'This action is unauthorized.']);
    }
}
