<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

#[Group('rest-api')]
class AccessControlExtensionTest extends RestApiTestCase
{
    #[Test]
    public function it_restricts_the_rows_through_the_extension_on_a_model_without_global_scope(): void
    {
        $this->grant(['view own posts' => ['Ada']]);

        $this->assertSame([], (new Post)->getGlobalScopes());
        $this->assertSame(['Release notes', 'Archive'], $this->searched('posts', $this->ada, 'title'));
    }

    #[Test]
    public function it_is_final_so_that_no_subclass_drops_the_restriction_of_the_rows_the_engine_or_the_cache(): void
    {
        $this->assertTrue((new ReflectionClass(AccessControlExtension::class))->isFinal());
    }
}
