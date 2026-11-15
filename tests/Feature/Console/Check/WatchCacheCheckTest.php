<?php

namespace Lomkit\Access\Tests\Feature\Console\Check;

use Lomkit\Access\Access;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Exceptions\UnwatchableReindexException;
use Lomkit\Access\Registry\WatchMap;
use Lomkit\Access\Tests\Fixtures\Check\Search\Watched\Controls\ShelvedDocumentControl;
use Lomkit\Access\Tests\Fixtures\Check\Search\Watched\Models\Shelf;
use Lomkit\Access\Tests\Fixtures\Search\MemoryEngine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class WatchCacheCheckTest extends SearchCheckTestCase
{
    private const string STALE = 'The [bootstrap/cache/access-reindex.php] cache lists other watched models than the reindexWhen() rules of the discovered controls, so the documents whose rule it misses keep stale access keys. Run "php artisan access:cache" again.';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->discover(['Search/Watched']);
        Access::registerPolicies();
        Access::trustSearchEngine(MemoryEngine::class);
    }

    #[Test]
    public function it_finds_no_problem_in_the_cache_that_access_cache_writes(): void
    {
        $this->artisan('access:cache')->assertOk()->run();

        [$code, $report] = $this->checkJson();

        $this->assertSame(0, $code);
        $this->assertSame([], $report['problems']);
    }

    #[Test]
    public function it_reports_a_cached_watch_map_that_misses_a_reindex_rule(): void
    {
        $this->writeCache([]);

        [$code, $report] = $this->checkJson();

        $this->assertSame(1, $code);
        $this->assertSame([
            ['kind' => 'stale_watch_cache', 'subject' => 'bootstrap/cache/access-reindex.php', 'message' => self::STALE],
            ['kind' => 'control_failure', 'subject' => ShelvedDocumentControl::class, 'message' => 'The ['.ShelvedDocumentControl::class.'] control throws while it is built or verified: '.UnwatchableReindexException::staleCache(ShelvedDocumentControl::class)->getMessage()],
        ], $report['problems']);
    }

    #[Test]
    public function it_reports_a_cached_watch_map_that_keeps_a_removed_rule(): void
    {
        $this->writeCache([Shelf::class => [ShelvedDocumentControl::class], 'App\Models\Project' => [ShelvedDocumentControl::class]]);

        [$code, $report] = $this->checkJson();

        $this->assertSame(1, $code);
        $this->assertSame([
            ['kind' => 'stale_watch_cache', 'subject' => 'bootstrap/cache/access-reindex.php', 'message' => self::STALE],
        ], $report['problems']);
    }

    #[Test]
    public function it_reports_a_cached_control_map_without_a_watch_map(): void
    {
        $this->app->make(DiscoveryCache::class)->write($this->app->make(ControlDiscovery::class)->map());

        [$code, $report] = $this->checkJson();

        $this->assertSame(1, $code);
        $this->assertSame([
            ['kind' => 'stale_watch_cache', 'subject' => 'bootstrap/cache/access-reindex.php', 'message' => 'The controls are cached, but the [bootstrap/cache/access-reindex.php] cache of the watched models is missing or unreadable, so no reindexWhen() rule reindexes a document when the model it watches changes. Run "php artisan access:cache" again.'],
        ], $report['problems']);
    }

    #[Test]
    public function it_reports_an_unreadable_watch_map(): void
    {
        $this->app->make(DiscoveryCache::class)->write($this->app->make(ControlDiscovery::class)->map());
        file_put_contents($this->app->bootstrapPath('cache/access-reindex.php'), "<?php throw new LogicException('Corrupt.');\n");

        [$code, $report] = $this->checkJson();

        $this->assertSame(1, $code);
        $this->assertSame(['stale_watch_cache', 'control_failure'], array_column($report['problems'], 'kind'));
        $this->assertStringStartsWith('The controls are cached, but the [bootstrap/cache/access-reindex.php] cache', $report['problems'][0]['message']);
    }

    #[Test]
    public function it_leaves_the_stale_cache_as_it_is(): void
    {
        $this->writeCache([]);
        $before = file_get_contents($this->app->bootstrapPath('cache/access-reindex.php'));

        $this->checkJson();

        $this->assertSame($before, file_get_contents($this->app->bootstrapPath('cache/access-reindex.php')));
    }

    /** @param  array<string, list<string>>  $watchers */
    private function writeCache(array $watchers): void
    {
        $cache = $this->app->make(DiscoveryCache::class);
        $cache->write($this->app->make(ControlDiscovery::class)->map());
        $map = WatchMap::fromCache($watchers);
        $this->assertNotNull($map);
        $cache->writeWatched($map);
    }
}
