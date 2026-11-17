<?php

namespace Lomkit\Access\Tests\Feature\Console\Check;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Lomkit\Access\Access;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Registry\WatchMap;
use Lomkit\Access\Search\Freshness\Inspection\FreshnessInspector;
use Lomkit\Access\Search\Freshness\Inspection\UnreadableKeyRelation;
use Lomkit\Access\Search\Freshness\Inspection\UnwatchedKeyRelation;
use Lomkit\Access\Tests\Fixtures\Search\MemoryEngine;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Broken\Controls\KioskControl;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Broken\Controls\StallControl;
use Lomkit\Access\Tests\Fixtures\Unconfigured\BrokenPivots\Consulate;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Controls\CountryControl;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Controls\OfficeControl;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Country;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Loading\Branch;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Office;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Pivots\Embassy;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Pivots\EmbassyCountry;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Searchable\Controls\DeskControl;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Unconfigured;
use Lomkit\Access\Tests\Fixtures\Unconfigured\Visit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class UnconfiguredConnectionCheckTest extends SearchCheckTestCase
{
    private const string TASKS_BOOTING_THE_TESTBENCH_SKELETON = 'config,routes';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Unconfigured];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Access::discoverControlsIn([$this->unconfigured('Controls'), $this->unconfigured('Searchable/Controls')]);
        Access::registerPolicies();
        Access::trustSearchEngine(MemoryEngine::class);
    }

    #[Test]
    public function it_checks_the_controls_of_models_whose_connection_is_not_configured(): void
    {
        [$code, $report] = $this->checkJson();

        $this->assertSame(0, $code);
        $this->assertSame([], $report['problems']);
        $this->assertSame([OfficeControl::class, DeskControl::class], array_values(array_filter(
            array_column($report['controls'], 'control'),
            fn (string $control): bool => $control !== CountryControl::class,
        )));
        $this->assertSame(
            ['relation' => 'country', 'delegates' => ['create' => 'view', 'view' => 'view'], 'allowed' => false, 'row_context' => null],
            $this->controlNamed($report, OfficeControl::class)['perimeters'][0]['parent'],
        );
        $this->assertSame(
            ['relation' => 'country', 'delegates' => ['view' => 'view'], 'allowed' => false, 'row_context' => null],
            $this->controlNamed($report, DeskControl::class)['perimeters'][0]['parent'],
        );
        $this->assertArrayNotHasKey('unconfigured', DB::getConnections());
    }

    #[Test]
    public function it_caches_the_controls_and_the_watched_models_of_a_searchable_child_whose_connection_is_not_configured(): void
    {
        $this->artisan('access:cache')->assertOk()->run();

        $this->assertFileExists($this->cachePath());
        $this->assertSame([Country::class => [DeskControl::class]], require $this->app->bootstrapPath('cache/access-reindex.php'));

        try {
            $this->assertSame(0, Artisan::call('optimize', ['--except' => self::TASKS_BOOTING_THE_TESTBENCH_SKELETON]));
            $this->assertMatchesRegularExpression('/access \.+ .*DONE/', Artisan::output());
        } finally {
            Artisan::call('optimize:clear');
        }
    }

    #[Test]
    public function it_reports_a_relation_to_a_reindexing_pivot_on_a_connection_that_is_not_configured(): void
    {
        [$code, $report] = $this->checkJson(['--models' => [$this->unconfigured('Pivots')]]);

        $this->assertSame(1, $code);
        $this->assertSame(2, $report['pivots']['relations']);
        $this->assertSame([
            ['kind' => 'pivot_without_using', 'subject' => Embassy::class, 'message' => 'The [allies] relation of the ['.Embassy::class.'] model reaches the [embassy_country] table without ->using(EmbassyCountry::class), so attach(), detach() and sync() through it fire no pivot event and the documents that ['.EmbassyCountry::class.'] reindexes keep their former keys. Add ->using(EmbassyCountry::class) to the relation.'],
        ], $report['problems']);
    }

    #[Test]
    public function it_reports_a_pivot_relation_that_throws_instead_of_skipping_it(): void
    {
        [$code, $report] = $this->checkJson(['--models' => [$this->unconfigured('BrokenPivots')]]);

        $this->assertSame(1, $code);
        $this->assertSame([
            ['kind' => 'unreadable_relation', 'subject' => Consulate::class, 'message' => 'The [countries] relation of the ['.Consulate::class.'] model throws when the package reads it without a database connection, so access:check cannot verify it: The countries relation is not wired yet. Fix the ['.Consulate::class.'::countries()] method.'],
        ], $report['problems']);
    }

    #[Test]
    public function it_inspects_the_freshness_of_a_relation_on_a_connection_that_is_not_configured(): void
    {
        $issues = FreshnessInspector::inspect(Branch::class)->issues();

        $this->assertCount(2, $issues);
        $this->assertInstanceOf(UnwatchedKeyRelation::class, $issues[0]);
        $this->assertSame(['country', Country::class], [$issues[0]->relation(), $issues[0]->related()]);
        $this->assertInstanceOf(UnreadableKeyRelation::class, $issues[1]);
        $this->assertSame([Branch::class, 'ministry', 'The ministry relation is not wired yet.'], [$issues[1]->owner(), $issues[1]->relation(), $issues[1]->reason()]);
        $this->assertArrayNotHasKey('unconfigured', DB::getConnections());
    }

    #[Test]
    public function it_reports_the_search_key_relations_without_crashing(): void
    {
        [$code, $report] = $this->checkJson(['--only' => [Branch::class], '--models' => [$this->unconfigured('Loading')]]);

        $this->assertSame(1, $code);
        $this->assertSame([
            ['kind' => 'search_freshness', 'subject' => Branch::class, 'message' => 'The [country] search keys of the ['.Branch::class.'] model read the [country] relation, but nothing reindexes the documents when ['.Country::class.'] changes, so they keep their former keys until they are reindexed. Declare reindexWhen(Country::class, ...) on the perimeter, use ReindexesSearch on the pivot, or call searchable() after the change.'],
            ['kind' => 'unreadable_relation', 'subject' => Branch::class, 'message' => 'The [ministry] relation of the ['.Branch::class.'] model, read by the [ministry] search keys of the ['.Branch::class.'] model, throws when the package reads it without a database connection, so access:check cannot verify it: The ministry relation is not wired yet. Fix the ['.Branch::class.'::ministry()] method.'],
        ], $report['problems']);
    }

    #[Test]
    public function it_saves_a_model_of_the_default_connection_without_the_cache_of_the_watched_models(): void
    {
        Visit::query()->create(['purpose' => 'Audit']);

        $this->assertSame(['Audit'], Visit::query()->pluck('purpose')->all());
        $this->assertFileDoesNotExist($this->app->bootstrapPath('cache/access-reindex.php'));
        $this->assertArrayNotHasKey('unconfigured', DB::getConnections());
    }

    #[Test]
    public function it_reports_each_control_that_throws_while_the_cached_watched_models_are_compared(): void
    {
        Access::discoverControlsIn([$this->unconfigured('Broken/Controls')]);
        $cache = $this->app->make(DiscoveryCache::class);
        $cache->write($this->app->make(ControlDiscovery::class)->map());
        $watched = WatchMap::fromCache([Country::class => [DeskControl::class]]);
        $this->assertNotNull($watched);
        $cache->writeWatched($watched);
        $failures = [
            ['kind' => 'control_failure', 'subject' => KioskControl::class, 'message' => 'The ['.KioskControl::class.'] control throws while it is built or verified: The country relation is not wired yet.'],
            ['kind' => 'control_failure', 'subject' => StallControl::class, 'message' => 'The ['.StallControl::class.'] control throws while it is built or verified: The country relation of the stall is not wired yet.'],
        ];

        [$onlyCode, $only] = $this->checkJson(['--only' => [Office::class]]);
        [$allCode, $all] = $this->checkJson();

        $this->assertSame(1, $onlyCode);
        $this->assertSame($failures, $only['problems']);
        $this->assertSame(1, $allCode);
        $this->assertSame($failures, $all['problems']);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function controlNamed(array $report, string $control): array
    {
        foreach ($report['controls'] as $entry) {
            if ($entry['control'] === $control) {
                return $entry;
            }
        }

        $this->fail("The report lists no [{$control}] control.");
    }

    private function unconfigured(string $path): string
    {
        return dirname(__DIR__, 3)."/Fixtures/Unconfigured/{$path}";
    }
}
