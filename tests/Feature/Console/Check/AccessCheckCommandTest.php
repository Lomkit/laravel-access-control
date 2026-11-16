<?php

namespace Lomkit\Access\Tests\Feature\Console\Check;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\ColumnBoundary;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Exceptions\DuplicateControlException;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Registry\ControlMap;
use Lomkit\Access\Registry\ControlRegistry;
use Lomkit\Access\Registry\WatchMap;
use Lomkit\Access\Tests\Fixtures\Check\Broken\Controls\GadgetControl;
use Lomkit\Access\Tests\Fixtures\Check\Broken\Controls\GizmoControl;
use Lomkit\Access\Tests\Fixtures\Check\Broken\Controls\WidgetControl;
use Lomkit\Access\Tests\Fixtures\Check\Claimed\Controls\RivalClaimedControl;
use Lomkit\Access\Tests\Fixtures\Check\Claimed\Declared\ClaimedControl;
use Lomkit\Access\Tests\Fixtures\Check\Claimed\Models\Claimed;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Controls\TicketControl;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Controls\TicketReplyControl;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Models\Ticket;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Models\TicketReply;
use Lomkit\Access\Tests\Fixtures\Check\Gated\GatedTicketReplyControl;
use Lomkit\Access\Tests\Fixtures\Check\Inherited\Controls\AlphaControl;
use Lomkit\Access\Tests\Fixtures\Check\Inherited\Controls\BetaControl;
use Lomkit\Access\Tests\Fixtures\Check\Inherited\Controls\TeamControl;
use Lomkit\Access\Tests\Fixtures\Check\Mislinked\Models\Stray;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\AdminPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\TeamPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Uncontrolled\Models\Orphan;
use Lomkit\Access\Tests\Fixtures\Discovery\Duplicates\FirstNoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Duplicates\SecondNoteControl;
use Lomkit\Access\Tests\Fixtures\Discovery\Models\Note;
use PHPUnit\Framework\Attributes\Test;

class AccessCheckCommandTest extends CheckTestCase
{
    #[Test]
    public function it_lists_the_controls_of_a_clean_app_and_finds_no_problem(): void
    {
        $this->discover(['Clean']);
        Access::registerPolicies();

        $this->artisan('access:check')
            ->expectsOutputToContain(TicketControl::class)
            ->expectsOutputToContain('#1.1 AdminPerimeter')
            ->expectsOutputToContain('#1.2 TeamPerimeter')
            ->expectsOutputToContain('#2 OwnPerimeter')
            ->expectsOutputToContain('#1 ThroughParent [ticket]')
            ->expectsOutputToContain('No problem found.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_shows_the_allowed_callback_of_a_through_parent_perimeter(): void
    {
        $this->discover(['Clean']);
        $this->app->instance(TicketReplyControl::class, new GatedTicketReplyControl);
        Access::registerPolicies();

        $this->artisan('access:check')
            ->expectsOutputToContain("sql, allowed, create from the parent's update")
            ->assertOk();

        [, $report] = $this->checkJson();
        $parents = array_values(array_filter(array_column(array_merge(...array_column($report['controls'], 'perimeters')), 'parent')));

        $this->assertSame([['relation' => 'ticket', 'delegates' => ['create' => 'update', 'update' => 'update', 'view' => 'view'], 'allowed' => true]], $parents);
    }

    #[Test]
    public function it_prints_the_report_as_json(): void
    {
        $this->discover(['Clean']);
        Access::registerPolicies();

        [$code, $report] = $this->checkJson();

        $this->assertSame(0, $code);
        $this->assertSame(['passed', 'discovery', 'controls', 'pivots', 'problems', 'notes'], array_keys($report));
        $this->assertTrue($report['passed']);
        $this->assertSame(
            ['paths' => ['app/Access/Controls', $this->fixture('Clean/Controls')], 'cached' => false, 'models' => ['app/Models'], 'scout_driver' => trait_exists(Searchable::class) ? 'collection' : null],
            $report['discovery'],
        );
        $this->assertSame([
            [
                'control' => TicketControl::class,
                'model' => Ticket::class,
                'perimeters' => [
                    ['position' => '#1.1', 'perimeter' => AdminPerimeter::class, 'mode' => 'memory', 'unrestricted' => true, 'search_keys' => null, 'parent' => null],
                    ['position' => '#1.2', 'perimeter' => TeamPerimeter::class, 'mode' => 'memory', 'unrestricted' => false, 'search_keys' => null, 'parent' => null],
                    ['position' => '#2', 'perimeter' => OwnPerimeter::class, 'mode' => 'sql', 'unrestricted' => false, 'search_keys' => null, 'parent' => null],
                ],
                'boundary' => ['boundary' => ColumnBoundary::class, 'mode' => 'memory', 'searchable' => true],
                'search' => ['searchable' => false, 'engine' => null],
            ],
            [
                'control' => TicketReplyControl::class,
                'model' => TicketReply::class,
                'perimeters' => [
                    ['position' => '#1', 'perimeter' => ThroughParent::class, 'mode' => 'sql', 'unrestricted' => false, 'search_keys' => null, 'parent' => ['relation' => 'ticket', 'delegates' => ['create' => 'update', 'update' => 'update', 'view' => 'view'], 'allowed' => false]],
                ],
                'boundary' => null,
                'search' => ['searchable' => false, 'engine' => null],
            ],
        ], $report['controls']);
        $this->assertSame([], $report['problems']);
        $this->assertSame([], $report['notes']);
    }

    #[Test]
    public function it_reports_a_control_that_throws_and_goes_on_with_the_others(): void
    {
        $this->discover(['Broken']);
        Access::registerPolicies();

        [$code, $report] = $this->checkJson();

        $this->assertSame(1, $code);
        $this->assertSame([WidgetControl::class], array_column($report['controls'], 'control'));
        $this->assertSame([
            ['kind' => 'control_failure', 'subject' => GadgetControl::class, 'message' => 'The ['.GadgetControl::class.'] control throws while it is built or verified: Lomkit\Access\Perimeters\Levels::of() received no level. A group lists its levels widest first, such as Levels::of(GlobalPerimeter::new()->...->unrestricted(), OwnPerimeter::new()->...), and only the highest level the reader reaches applies.'],
            ['kind' => 'control_failure', 'subject' => GizmoControl::class, 'message' => 'The ['.GizmoControl::class.'] control throws while it is built or verified: The perimeters() or boundary() method of the ['.GizmoControl::class.'] control built a different declaration twice. The control keeps the perimeters and the boundary it builds first for the whole process, a queue worker or an Octane worker included, so perimeters() and boundary() must not read the signed-in user, the request, the tenant or the clock while they build them. Read them inside the callbacks, from the reader they receive. The state of a perimeter class is compared too: its constructor and its withers must set it from pure values while perimeters() builds the perimeter, and a hook must never change it.'],
        ], $report['problems']);
    }

    #[Test]
    public function it_reports_two_controls_for_one_model(): void
    {
        Access::discoverControlsIn([dirname(__DIR__, 3).'/Fixtures/Discovery/Duplicates']);

        [$code, $report] = $this->checkJson();

        $this->assertSame(1, $code);
        $this->assertSame([
            ['kind' => 'duplicate_control', 'subject' => Note::class, 'message' => DuplicateControlException::forModel(Note::class, FirstNoteControl::class, SecondNoteControl::class)->getMessage()],
        ], $report['problems']);
    }

    #[Test]
    public function it_reports_a_controlled_by_attribute_that_names_another_control_than_the_discovered_one(): void
    {
        $this->discover(['Claimed']);

        [$code, $report] = $this->checkJson();

        $this->assertSame(1, $code);
        $this->assertSame([
            ['kind' => 'duplicate_control', 'subject' => Claimed::class, 'message' => DuplicateControlException::forModel(Claimed::class, ClaimedControl::class, RivalClaimedControl::class)->getMessage()],
        ], $report['problems']);
    }

    #[Test]
    public function it_reports_a_model_that_uses_has_control_without_a_control(): void
    {
        $this->discover(['Clean']);
        Access::registerPolicies();

        $this->artisan('access:check', ['--models' => [$this->fixture('Uncontrolled/Models')]])
            ->expectsOutputToContain('The ['.Orphan::class.'] model uses HasControl, but no control is discovered for it, so controlled() and its policy throw MissingControlException.')
            ->expectsOutputToContain('1 problem found.')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_reports_a_controlled_by_attribute_that_names_the_control_of_another_model(): void
    {
        $this->discover(['Clean']);
        Access::registerPolicies();

        [$code, $report] = $this->checkJson(['--models' => [$this->fixture('Mislinked/Models')]]);

        $this->assertSame(1, $code);
        $this->assertSame([
            ['kind' => 'invalid_controlled_by', 'subject' => Stray::class, 'message' => 'The #[ControlledBy] attribute of the ['.Stray::class.'] model names the ['.TicketControl::class.'] control, which controls the ['.Ticket::class.'] model. Name the control whose $model is ['.Stray::class.'].'],
        ], $report['problems']);
    }

    #[Test]
    public function it_reports_a_cache_that_maps_other_controls_than_the_discovered_ones(): void
    {
        $this->discover(['Clean']);
        Access::registerPolicies();
        $cache = $this->app->make(DiscoveryCache::class);
        $cache->write(new ControlMap([Ticket::class => TicketControl::class]));
        $cache->writeWatched(WatchMap::of($this->app->make(ControlRegistry::class), new ControlMap));

        [$code, $report] = $this->checkJson();

        $this->assertSame(1, $code);
        $this->assertTrue($report['discovery']['cached']);
        $this->assertSame([
            ['kind' => 'stale_control_cache', 'subject' => 'bootstrap/cache/access-controls.php', 'message' => 'The [bootstrap/cache/access-controls.php] cache maps the models to other controls than the ones discovered now, so the application uses the cached ones. Run "php artisan access:cache" again, or "php artisan access:clear".'],
        ], $report['problems']);
    }

    #[Test]
    public function it_reports_a_cache_written_before_a_directory_was_added(): void
    {
        $this->discover(['Clean']);
        Access::registerPolicies();
        $this->artisan('access:cache')->assertOk()->run();

        $this->discover(['Drafts']);
        [$code, $report] = $this->checkJson();

        $this->assertSame(1, $code);
        $this->assertContains('stale_control_cache', array_column($report['problems'], 'kind'));
        $this->assertSame(['app/Access/Controls', $this->fixture('Clean/Controls'), $this->fixture('Drafts/Controls')], $report['discovery']['paths']);
    }

    #[Test]
    public function it_notes_a_base_control_whose_should_perimeters_put_its_subclasses_in_memory(): void
    {
        $this->discover(['Inherited']);
        Access::registerPolicies();

        [$code, $report] = $this->checkJson();

        $this->assertSame(0, $code);
        $this->assertSame([
            ['kind' => 'inherited_memory_perimeters', 'subject' => TeamControl::class, 'message' => 'The ['.TeamControl::class.'] base control declares perimeters with a should() callback (['.TeamPerimeter::class.']), so its 2 controls ['.AlphaControl::class.', '.BetaControl::class.'] check them in memory. Override perimeters() in a control whose rows should be checked in SQL.'],
        ], $report['notes']);
    }

    #[Test]
    public function it_checks_only_the_named_models_or_controls(): void
    {
        $this->discover(['Clean', 'Broken']);
        Access::registerPolicies();

        [$byModel] = [$this->checkJson(['--only' => [TicketReply::class]])[1]];
        [$code, $byControl] = $this->checkJson(['--only' => ['\\'.TicketControl::class, WidgetControl::class]]);

        $this->assertSame([TicketReplyControl::class], array_column($byModel['controls'], 'control'));
        $this->assertSame(0, $code);
        $this->assertSame([TicketControl::class, WidgetControl::class], array_column($byControl['controls'], 'control'));
    }

    #[Test]
    public function it_refuses_a_filter_that_names_no_control(): void
    {
        $this->discover(['Clean']);

        $this->artisan('access:check', ['--only' => ['App\Models\Invoice']])
            ->expectsOutputToContain('No discovered control or controlled model is named [App\Models\Invoice].')
            ->assertExitCode(2);
    }

    #[Test]
    public function it_runs_no_query_writes_no_cache_and_dispatches_no_job(): void
    {
        $this->discover(['Clean', 'Broken', 'Inherited']);
        Bus::fake();
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        Artisan::call('access:check', ['--models' => [$this->fixture('Uncontrolled/Models')]]);
        Artisan::call('access:check', ['--json' => true]);

        $this->assertSame([], $queries);
        $this->assertFileDoesNotExist($this->cachePath());
        Bus::assertNothingDispatched();
    }
}
