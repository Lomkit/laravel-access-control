<?php

namespace Lomkit\Access\Tests\Feature\Scopes;

use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\Search;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ScoutMissingControlTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [
            new class extends Search
            {
                #[\Override]
                public function controls(): array
                {
                    return [];
                }
            },
        ];
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
    public function it_throws_on_a_controlled_search_without_a_control(): void
    {
        $this->expectException(MissingControlException::class);
        $this->expectExceptionMessage(sprintf('No control is registered for the [%s] model.', Article::class));

        Article::search('Scout')->controlled();
    }
}
