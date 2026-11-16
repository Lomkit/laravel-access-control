<?php

namespace Lomkit\Access\Tests\Feature\RestApi;

use Lomkit\Access\Integrations\RestApi\AccessControlExtension;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\RestApi\BranchingExtension;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('rest-api')]
class ExtensionBranchesTest extends RestApiTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->grant(['view own posts' => ['Ada'], 'update client posts' => ['Ada']]);
    }

    #[Test]
    public function it_restricts_the_rows_through_the_extension_on_a_model_without_global_scope(): void
    {
        $this->assertSame([], (new Post)->getGlobalScopes());
        $this->assertSame(['Release notes', 'Archive'], $this->searched('posts', $this->ada, 'title'));
    }

    #[Test]
    public function it_lets_a_subclass_of_the_extension_or_controlled_branches_inside_the_restriction(): void
    {
        config(['rest.extensions' => [BranchingExtension::class], 'rest.disabled_extensions' => [AccessControlExtension::class]]);

        $this->assertSame(['Release notes', 'Roadmap', 'Archive'], $this->searched('posts', $this->ada, 'title'));
        $this->assertSame(
            ['Release notes', 'Roadmap', 'Archive'],
            Post::controlledFor($this->ada)->orWhere(fn ($branch) => $branch->controlledFor($this->ada, 'update'))->orderBy('id')->pluck('title')->all(),
        );
    }
}
