<?php

namespace Lomkit\Access\Tests\Feature\Fixtures;

use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\Search;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SearchFixtureTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Search];
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
    public function it_searches_the_articles(): void
    {
        Article::factory()->createOne(['title' => 'Scout primer']);
        Article::factory()->createOne(['title' => 'Policy guide']);

        $this->assertSame(['Scout primer'], Article::search('Scout')->get()->pluck('title')->all());
    }
}
