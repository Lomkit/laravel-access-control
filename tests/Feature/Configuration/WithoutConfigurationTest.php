<?php

namespace Lomkit\Access\Tests\Feature\Configuration;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\AccessServiceProvider;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RecordingConfig;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\Search;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class WithoutConfigurationTest extends PackageTestCase
{
    protected User $ada;

    protected Post $releaseNotes;

    protected Post $roadmap;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Search];
    }

    #[\Override]
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            ScoutServiceProvider::class,
        ];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $acme = Client::factory()->createOne(['name' => 'Acme']);
        $globex = Client::factory()->createOne(['name' => 'Globex']);
        $this->ada = User::factory()->for($acme)->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        $hank = User::factory()->for($globex)->createOne(['name' => 'Hank', 'email' => 'hank@example.com']);
        $website = Project::factory()->for($acme)->createOne(['name' => 'Website']);
        $warehouse = Project::factory()->for($globex)->createOne(['name' => 'Warehouse']);
        $this->releaseNotes = Post::factory()->for($website)->for($this->ada, 'author')->createOne(['title' => 'Release notes']);
        $this->roadmap = Post::factory()->for($warehouse)->for($hank, 'author')->createOne(['title' => 'Roadmap']);
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer']);
    }

    #[Test]
    public function it_boots_without_any_configuration(): void
    {
        Gate::define('view client posts', fn (User $user): bool => true);

        $this->assertFalse(config()->has('access-control'));
        $this->assertTrue($this->ada->can('viewAny', Post::class));
        $this->assertTrue($this->ada->can('view', $this->releaseNotes));
        $this->assertSame(['Release notes'], Post::controlledFor($this->ada)->pluck('title')->all());
    }

    #[Test]
    public function it_publishes_no_configuration(): void
    {
        $this->assertSame([], ServiceProvider::pathsToPublish(AccessServiceProvider::class));
        $this->assertNotContains('access-control-config', ServiceProvider::publishableGroups());
    }

    #[Test]
    public function it_reads_no_access_control_key(): void
    {
        $config = new RecordingConfig(config()->all());
        $this->app->instance('config', $config);
        Gate::define('view own posts', fn (User $user): bool => true);
        Gate::define('view own articles', fn (User $user): bool => true);
        $this->actingAs($this->ada);

        foreach (['viewAny', 'create'] as $policyMethod) {
            $this->ada->can($policyMethod, Post::class);
        }

        foreach (['view', 'update', 'delete', 'restore', 'forceDelete', 'approve'] as $policyMethod) {
            $this->ada->can($policyMethod, $this->releaseNotes);
        }

        $this->assertSame(['Release notes'], Post::controlled()->pluck('title')->all());
        $this->assertSame(['Scout primer'], Article::search('Scout')->controlled()->get()->pluck('title')->all());
        $this->assertNotSame([], $config->readKeys);
        $this->assertSame([], array_values(array_filter(
            $config->readKeys,
            fn (string $key): bool => str_starts_with($key, 'access-control'),
        )));
    }

    #[Test]
    public function it_ignores_a_methods_map_left_in_the_application_configuration(): void
    {
        config([
            'access-control.methods.viewAny' => 'update',
            'access-control.methods.view' => 'update',
            'access-control.methods.update' => 'view',
        ]);
        Gate::define('view client posts', fn (User $user): bool => true);

        $this->assertTrue($this->ada->can('viewAny', Post::class));
        $this->assertTrue($this->ada->can('view', $this->releaseNotes));
        $this->assertFalse($this->ada->can('update', $this->releaseNotes));
        $this->assertFalse($this->ada->can('view', $this->roadmap));
        $this->assertSame(['Release notes'], Post::controlledFor($this->ada)->pluck('title')->all());
    }
}
