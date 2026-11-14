<?php

namespace Lomkit\Access\Tests\Feature\Configuration;

use Illuminate\Support\Facades\Gate;
use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\RecordingConfig;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\Search;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class WithoutConfigurationSearchTest extends PackageTestCase
{
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

    #[Test]
    public function it_reads_no_access_control_key_for_a_controlled_search(): void
    {
        $ada = User::factory()->for(Client::factory()->createOne(['name' => 'Acme']))->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        Article::factory()->createOne(['author_id' => $ada->getKey(), 'title' => 'Scout primer']);
        $config = new RecordingConfig(config()->all());
        $this->app->instance('config', $config);
        Gate::define('view own articles', fn (User $user): bool => true);
        $this->actingAs($ada);

        $this->assertSame(['Scout primer'], Article::search('Scout')->controlled()->get()->pluck('title')->all());
        $this->assertSame([], array_values(array_filter(
            $config->readKeys,
            fn (string $key): bool => str_starts_with($key, 'access-control'),
        )));
    }
}
