<?php

namespace Lomkit\Access\Tests\Feature\Scopes;

use Laravel\Scout\ScoutServiceProvider;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Tests\Fixtures\Search\Page;
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
    public function it_throws_on_a_controlled_search_without_a_control(): void
    {
        $this->expectException(MissingControlException::class);
        $this->expectExceptionMessage(sprintf('No control is registered for the [%s] model.', Page::class));

        Page::search('Scout')->controlled();
    }
}
