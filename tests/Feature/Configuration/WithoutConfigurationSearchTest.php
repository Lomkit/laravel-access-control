<?php

namespace Lomkit\Access\Tests\Feature\Configuration;

use Lomkit\Access\Tests\Fixtures\RecordingConfig;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class WithoutConfigurationSearchTest extends SearchTestCase
{
    #[Test]
    public function it_reads_no_access_control_key_to_index_and_search(): void
    {
        $ada = $this->user('Ada');
        $config = new RecordingConfig(config()->all());
        $this->app->instance('config', $config);
        $this->allow(['view own articles']);
        Article::factory()->createOne(['author_id' => $ada->getKey(), 'title' => 'Scout primer']);
        $this->actingAs($ada);

        $this->assertSame(['Scout primer'], $this->searchedTitles());
        $this->assertSame([], array_values(array_filter(
            $config->readKeys,
            fn (string $key): bool => str_starts_with($key, 'access-control'),
        )));
    }
}
