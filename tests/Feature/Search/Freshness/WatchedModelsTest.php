<?php

namespace Lomkit\Access\Tests\Feature\Search\Freshness;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\UnwatchableReindexException;
use Lomkit\Access\Registry\ControlVersion;
use Lomkit\Access\Search\Freshness\ReindexWatchedRows;
use Lomkit\Access\Tests\Fixtures\Search\ArchiveFolder;
use Lomkit\Access\Tests\Fixtures\Search\Folder;
use Lomkit\Access\Tests\Fixtures\Search\FreshnessTestCase;
use Lomkit\Access\Tests\Fixtures\Search\NoticeControl;
use Lomkit\Access\Tests\Fixtures\Search\Page;
use Lomkit\Access\Tests\Fixtures\Search\Watched\PageControl;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class WatchedModelsTest extends FreshnessTestCase
{
    #[\Override]
    protected function tearDown(): void
    {
        File::delete([$this->controlsCachePath(), $this->watchedCachePath()]);

        parent::tearDown();
    }

    protected function controlsCachePath(): string
    {
        return $this->app->bootstrapPath('cache/access-controls.php');
    }

    protected function watchedCachePath(): string
    {
        return $this->app->bootstrapPath('cache/access-reindex.php');
    }

    protected function page(Folder $folder): Page
    {
        return Page::withoutSyncingToSearch(fn (): Page => Page::query()->create(['folder_id' => $folder->getKey(), 'title' => 'Scout primer']));
    }

    protected function pageAccess(Page $page): mixed
    {
        return $this->engine->documents[$page->searchableAs()][(string) $page->getKey()]['_access'] ?? null;
    }

    protected function startFreshProcess(): void
    {
        $this->forgetRegistry();
        PageControl::$declarations = 0;
        $this->engine->documents = [];
    }

    #[Test]
    public function it_reindexes_the_rows_of_a_control_that_was_never_built_in_the_process(): void
    {
        $folder = $this->folder($this->acme);
        $page = $this->page($folder);
        $this->startFreshProcess();

        $this->assertSame(0, PageControl::$declarations);
        $folder->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame(["client.{$this->globex->getKey()}"], $this->pageAccess($page));
    }

    #[Test]
    public function it_reindexes_the_rows_of_a_subclass_of_the_watched_model(): void
    {
        $folder = ArchiveFolder::query()->create(['client_id' => $this->acme->getKey(), 'name' => 'Archive']);
        $page = $this->page($folder);
        $this->startFreshProcess();

        $folder->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame(["client.{$this->globex->getKey()}"], $this->pageAccess($page));
    }

    #[Test]
    public function it_builds_no_control_for_a_model_nobody_watches_once_the_watch_map_is_cached(): void
    {
        $folder = $this->folder($this->acme);
        $page = $this->page($folder);
        $this->artisan('access:cache')->assertOk()->run();
        $this->startFreshProcess();

        $this->ada->update(['name' => 'Ada Lovelace']);
        $this->assertSame(0, PageControl::$declarations);

        $folder->update(['client_id' => $this->globex->getKey()]);
        $this->assertSame(["client.{$this->globex->getKey()}"], $this->pageAccess($page));
    }

    #[Test]
    public function it_caches_the_controls_that_watch_each_model(): void
    {
        $this->artisan('access:cache')->assertOk()->run();

        $cached = require $this->watchedCachePath();

        $this->assertSame([Folder::class => [PageControl::class]], $cached['watchers']);
        $this->assertSame(ControlVersion::of(PageControl::class), $cached['versions'][PageControl::class]);
        $this->assertArrayHasKey(NoticeControl::class, $cached['versions']);
    }

    #[Test]
    public function it_clears_the_cached_watch_map(): void
    {
        $this->artisan('access:cache')->assertOk()->run();

        $this->artisan('access:clear')->assertOk()->run();

        $this->assertFileDoesNotExist($this->watchedCachePath());
    }

    #[Test]
    public function it_refuses_a_control_whose_reindex_the_cached_watch_map_does_not_know(): void
    {
        $this->writeWatchedCache([], [PageControl::class => $this->versionOf(PageControl::class)]);
        $this->forgetRegistry();

        $this->expectException(UnwatchableReindexException::class);
        $this->expectExceptionMessage(sprintf('The [%s] control declares a reindexWhen() that the access cache does not know', PageControl::class));

        Access::control(PageControl::class);
    }

    #[Test]
    public function it_queues_nothing_for_a_model_nobody_watches(): void
    {
        Queue::fake();

        $this->ada->update(['name' => 'Ada Lovelace']);
        $this->acme->delete();

        Queue::assertNotPushed(ReindexWatchedRows::class);
    }

    #[Test]
    public function it_builds_the_watch_map_again_when_a_cached_control_changed_since_the_cache(): void
    {
        $folder = $this->folder($this->acme);
        $page = $this->page($folder);
        $this->writeWatchedCache([], [PageControl::class => $this->versionOf(PageControl::class) - 1]);
        $this->startFreshProcess();

        $folder->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame(["client.{$this->globex->getKey()}"], $this->pageAccess($page));
    }

    #[Test]
    public function it_builds_the_watch_map_again_from_a_cache_it_cannot_read(): void
    {
        $folder = $this->folder($this->acme);
        $page = $this->page($folder);
        File::put($this->watchedCachePath(), '<?php return [\'watchers\' => []];'.PHP_EOL);
        $this->startFreshProcess();

        $folder->update(['client_id' => $this->globex->getKey()]);

        $this->assertSame(["client.{$this->globex->getKey()}"], $this->pageAccess($page));
    }

    /**
     * @param  array<string, list<string>>  $watchers
     * @param  array<string, int>  $versions
     */
    protected function writeWatchedCache(array $watchers, array $versions): void
    {
        File::put($this->watchedCachePath(), '<?php return '.var_export(['watchers' => $watchers, 'versions' => $versions], true).';'.PHP_EOL);
    }

    /** @param  class-string  $control */
    protected function versionOf(string $control): int
    {
        return ControlVersion::of($control);
    }

    #[Test]
    public function it_refuses_a_cached_watcher_that_is_no_longer_a_control(): void
    {
        $folder = $this->folder($this->acme);
        $this->writeWatchedCache([Folder::class => ['App\\Access\\Controls\\GoneControl']], []);
        $this->startFreshProcess();

        $this->expectException(UnwatchableReindexException::class);
        $this->expectExceptionMessage('The [App\\Access\\Controls\\GoneControl] control declares a reindexWhen() that the access cache does not know');

        $folder->update(['client_id' => $this->globex->getKey()]);
    }
}
