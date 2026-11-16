<?php

namespace Lomkit\Access\Tests\Feature\Decisions;

use Closure;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Decisions\DecisionMemo;
use Lomkit\Access\Decisions\Verdict;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\Fixtures\Framework\Framework;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class SqlWriteFlushTest extends DecisionTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog, new Framework];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('queue.connections.database', ['driver' => 'database', 'connection' => null, 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90]);
        $app['config']->set('queue.batching', ['database' => null, 'table' => 'job_batches']);
        $app['config']->set('queue.failed', ['driver' => 'database-uuids', 'database' => null, 'table' => 'failed_jobs']);
        $app['config']->set('session.driver', 'database');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ConfiguredPostControl::bind($this->ownInSql());
    }

    /** @return array<string, array{Closure(self): mixed}> */
    public static function writes(): array
    {
        return [
            'a mass update' => [fn (self $test) => Post::query()->where('title', 'Roadmap')->update(['title' => 'Plan'])],
            'a mass delete' => [fn (self $test) => Post::query()->where('title', 'Elsewhere')->delete()],
            'a quiet save' => [fn (self $test) => $test->teamPost->forceFill(['title' => 'Quiet'])->saveQuietly()],
            'a save without events' => [fn (self $test) => Client::withoutEvents(fn () => Client::query()->firstOrFail()->update(['name' => 'Silent']))],
            'a query builder update' => [fn (self $test) => DB::table('posts')->update(['title' => 'Mass update'])],
            'a query builder insert' => [fn (self $test) => DB::table('clients')->insert(['name' => 'Initech'])],
            'a pivot attach' => [fn (self $test) => $test->adaPost->sharedWith()->attach($test->grace)],
            'a pivot sync' => [fn (self $test) => $test->adaPost->sharedWith()->sync([$test->grace->getKey()])],
            'a raw statement' => [fn (self $test) => DB::statement('update posts set title = ? where title = ?', ['Raw', 'Roadmap'])],
            'an unprepared statement' => [fn (self $test) => DB::unprepared("update posts set title = 'Raw' where title = 'Roadmap'")],
            'a common table expression' => [fn (self $test) => DB::select('with listed as (select id from posts) select count(*) as total from listed')],
            'two statements on a framework table and a model table' => [fn (self $test) => DB::unprepared('delete from '.DB::getQueryGrammar()->wrapTable('cache').'; delete from '.DB::getQueryGrammar()->wrapTable('post_shares'))],
            'a select followed by a write' => [fn (self $test) => DB::unprepared('select 1; delete from '.DB::getQueryGrammar()->wrapTable('post_shares'))],
        ];
    }

    #[Test]
    #[DataProvider('writes')]
    public function it_forgets_every_decision_after_a_write(Closure $write): void
    {
        $this->remember();

        $write($this);

        $this->assertForgotten();
    }

    #[Test]
    public function it_forgets_every_decision_after_a_pivot_detach(): void
    {
        $this->adaPost->sharedWith()->attach($this->grace);
        $this->remember();

        $this->adaPost->sharedWith()->detach($this->grace);

        $this->assertForgotten();
    }

    #[Test]
    public function it_forgets_every_decision_after_an_eloquent_write_while_events_are_faked(): void
    {
        $this->remember();
        Event::fake();

        Client::query()->firstOrFail()->update(['name' => 'Acme Corp']);

        Event::assertDispatched('eloquent.saved: '.Client::class);
        $this->assertForgotten();
    }

    /** @return array<string, array{Closure(self): mixed}> */
    public static function frameworkWrites(): array
    {
        return [
            'a cache entry' => [function (self $test): void {
                Cache::store('database')->put('report', 'ready', 60);
                Cache::store('database')->increment('hits');
                Cache::store('database')->forget('report');
                Cache::store('database')->flush();
            }],
            'a cache lock' => [function (self $test): void {
                $lock = Cache::store('database')->lock('report', 10);
                $test->assertTrue($lock->get());
                $test->assertFalse(Cache::store('database')->lock('report', 10)->get());
                $lock->release();
            }],
            'a session' => [function (self $test): void {
                $handler = $test->app->make('session')->driver('database')->getHandler();
                $handler->write('session-1', 'payload');
                $handler->write('session-1', 'payload changed');
                $handler->destroy('session-1');
                $handler->gc(60);
            }],
            'a queued job' => [function (self $test): void {
                Queue::connection('database')->pushRaw('{"uuid":"job-1","displayName":"Report","job":"Report","data":[]}');
                $job = Queue::connection('database')->pop();
                $test->assertInstanceOf(Job::class, $job);
                $job->delete();
            }],
            'a failed job' => [fn (self $test) => $test->app->make('queue.failer')->log('database', 'default', '{"uuid":"job-2"}', new RuntimeException('Failed'))],
            'a job batch' => [function (self $test): void {
                $repository = $test->app->make(BatchRepository::class);
                $batch = $repository->store(new PendingBatch($test->app, collect()));
                $repository->incrementTotalJobs($batch->id, 1);
                $repository->delete($batch->id);
            }],
        ];
    }

    #[Test]
    #[DataProvider('frameworkWrites')]
    public function it_keeps_the_decisions_through_a_write_to_a_framework_table(Closure $write): void
    {
        $this->remember();

        $write($this);

        $this->assertKept();
    }

    #[Test]
    public function it_keeps_the_decisions_through_reads(): void
    {
        $this->remember();

        Post::query()->get();
        DB::select('select count(*) as total from posts');
        Post::query()->where('title', 'Roadmap')->lockForUpdate()->first();
        Post::query()->select('title')->union(Post::query()->select('title'))->get();

        $this->assertKept();
    }

    #[Test]
    public function it_forgets_the_decisions_after_a_write_to_a_framework_table_on_another_connection(): void
    {
        config(['cache.stores.database.connection' => 'elsewhere']);
        $this->remember();

        DB::table('cache')->insert(['key' => 'report', 'value' => 'ready', 'expiration' => 60]);

        $this->assertForgotten();
    }

    #[Test]
    public function it_forgets_the_decisions_after_a_write_to_a_table_no_database_driver_declares(): void
    {
        config(['cache.stores' => ['array' => ['driver' => 'array']], 'session.driver' => 'array']);
        $this->remember();

        DB::table('cache')->insert(['key' => 'report', 'value' => 'ready', 'expiration' => 60]);
        $this->assertForgotten();

        $this->remember();
        DB::table('sessions')->insert(['id' => 'session-1', 'payload' => 'payload', 'last_activity' => 60]);
        $this->assertForgotten();
    }

    #[Test]
    public function it_forgets_the_decisions_kept_in_the_container_of_a_cloned_application(): void
    {
        $this->assertFalse($this->app->resolved(DecisionMemo::class));
        $sandbox = clone $this->app;
        $sandbox->instance('app', $sandbox);
        Container::setInstance($sandbox);

        try {
            $this->assertTrue($this->ada->can('update', $this->adaPost));
            $this->assertSame(1, $sandbox->make(DecisionMemo::class)->reachCount());

            DB::table('posts')->update(['title' => 'Mass update']);

            $this->assertSame(0, $sandbox->make(DecisionMemo::class)->reachCount());

            $this->assertTrue($this->ada->can('update', $this->adaPost));
            $this->adaPost->forceFill(['title' => 'Saved'])->save();

            $this->assertSame(0, $sandbox->make(DecisionMemo::class)->reachCount());
        } finally {
            Container::setInstance($this->app);
        }
    }

    #[Test]
    public function it_never_builds_the_memo_for_a_write(): void
    {
        $this->assertFalse($this->app->resolved(DecisionMemo::class));

        DB::table('posts')->update(['title' => 'Mass update']);

        $this->assertFalse($this->app->resolved(DecisionMemo::class));
    }

    private function remember(): void
    {
        $this->assertTrue($this->ada->can('update', $this->adaPost));
        $this->record($this->ada, 'update', $this->teamPost, Verdict::Granted);
        $this->assertSame(1, $this->memo()->count());
        $this->assertSame(1, $this->memo()->reachCount());
    }

    private function assertForgotten(): void
    {
        $this->assertSame(0, $this->memo()->count());
        $this->assertSame(0, $this->memo()->reachCount());
        $this->assertFalse(Access::controlFor(Post::class)->applies($this->ada, Ability::update(), $this->teamPost));
    }

    private function assertKept(): void
    {
        $this->assertSame(1, $this->memo()->count());
        $this->assertSame(1, $this->memo()->reachCount());
    }
}
