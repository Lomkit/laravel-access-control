<?php

namespace Lomkit\Access\Tests\Feature\Console\Check;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Facades\Gate;
use Lomkit\Access\Access;
use Lomkit\Access\Console\Check\CheckReport;
use Lomkit\Access\Console\Check\HumanReport;
use Lomkit\Access\Console\Check\Inventory\DiscoveryInventory;
use Lomkit\Access\Console\Check\Notes\ModelWithoutPolicy;
use Lomkit\Access\Console\Check\Notes\Note;
use Lomkit\Access\Console\Check\Notes\PolicyOutsideControl;
use Lomkit\Access\Console\Check\Problems\UncontrolledModel;
use Lomkit\Access\Discovery\ControlDiscovery;
use Lomkit\Access\Discovery\DiscoveryCache;
use Lomkit\Access\Policies\AutomaticPolicy;
use Lomkit\Access\Tests\Fixtures\Blog\Comment;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Models\Ticket;
use Lomkit\Access\Tests\Fixtures\Check\Clean\Models\TicketReply;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\AlphaPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\BasePolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\BravoPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\CharliePolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\DeltaPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\EchoPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\FoxtrotPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\GolfPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\HotelPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\IndiaPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\JuliettPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\KiloPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Crowded\LimaPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Policies\HandWrittenTicketPolicy;
use Lomkit\Access\Tests\Fixtures\Check\Policies\TicketPolicyNamingReplies;
use Lomkit\Access\Tests\Fixtures\Check\Uncontrolled\Models\Orphan;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class CheckReadabilityTest extends CheckTestCase
{
    private const array CROWDED = [
        AlphaPolicy::class, BravoPolicy::class, CharliePolicy::class, DeltaPolicy::class, EchoPolicy::class, FoxtrotPolicy::class,
        GolfPolicy::class, HotelPolicy::class, IndiaPolicy::class, JuliettPolicy::class, KiloPolicy::class, LimaPolicy::class,
    ];

    /**
     * @param  list<Note>  $notes
     * @param  list<UncontrolledModel>  $problems
     */
    protected function report(array $notes, array $problems = []): CheckReport
    {
        $discovery = DiscoveryInventory::of($this->app, $this->app->make(ControlDiscovery::class), $this->app->make(DiscoveryCache::class), [], 0, null);

        return new CheckReport($discovery, [], 0, $problems, $notes);
    }

    protected function rendered(CheckReport $report): string
    {
        $output = new BufferedOutput;
        (new HumanReport($report))->render(new Factory(new OutputStyle(new ArrayInput([]), $output)));

        return $output->fetch();
    }

    /** @return list<Note> */
    protected function crowdedNotes(): array
    {
        return array_map(fn (string $policy): Note => new PolicyOutsideControl(Ticket::class, $policy), self::CROWDED);
    }

    #[Test]
    public function it_groups_twelve_notes_of_one_kind_in_one_line_with_their_count(): void
    {
        $output = $this->rendered($this->report($this->crowdedNotes()));

        $this->assertStringContainsString(sprintf(
            'policy_outside_control: 12 policies, 10 extending %s (first 3: %s, %s, %s); --json lists them all',
            BasePolicy::class,
            AlphaPolicy::class,
            BravoPolicy::class,
            CharliePolicy::class,
        ), $output);
        $this->assertSame(1, substr_count($output, 'policy_outside_control'));
        $this->assertStringNotContainsString('does not extend ControlledPolicy', $output);
        $this->assertStringContainsString('notes ', $output);
        $this->assertMatchesRegularExpression('/notes \.+ 12/', $output);
    }

    #[Test]
    public function it_names_no_parent_that_only_the_framework_or_the_package_declares(): void
    {
        $models = [Ticket::class, TicketReply::class, Orphan::class, Post::class, Comment::class, Project::class];

        $output = $this->rendered($this->report(array_map(fn (string $model): Note => new ModelWithoutPolicy($model), $models)));

        $this->assertStringContainsString(sprintf('model_without_policy: 6 models (first 3: %s, %s, %s); --json lists them all', Ticket::class, TicketReply::class, Orphan::class), $output);
        $this->assertStringNotContainsString('extending', $output);
    }

    #[Test]
    public function it_lists_five_notes_of_one_kind_one_by_one(): void
    {
        $notes = array_slice($this->crowdedNotes(), 0, 5);

        $output = $this->rendered($this->report([...$notes, new ModelWithoutPolicy(TicketReply::class)]));

        $this->assertSame(5, substr_count($output, 'does not extend ControlledPolicy'));
        $this->assertStringNotContainsString('--json lists them all', $output);
        $this->assertStringContainsString('No policy is registered for the ['.TicketReply::class.'] model', $output);
    }

    #[Test]
    public function it_never_groups_the_problems(): void
    {
        $problems = array_map(fn (int $index): UncontrolledModel => new UncontrolledModel("App\\Models\\Orphan{$index}"), range(1, 12));

        $output = $this->rendered($this->report([], $problems));

        $this->assertSame(12, substr_count($output, 'uses HasControl, but no control is discovered for it'));
        $this->assertStringNotContainsString('--json lists them all', $output);
    }

    #[Test]
    public function it_keeps_every_note_in_the_json_report(): void
    {
        $notes = $this->crowdedNotes();

        $this->assertSame(
            array_map(fn (Note $note): array => ['kind' => 'policy_outside_control', 'subject' => $note->subject(), 'message' => $note->message()], $notes),
            $this->report($notes)->toArray()['notes'],
        );
    }

    #[Test]
    public function it_prints_the_same_json_whether_the_human_report_groups_the_notes_or_not(): void
    {
        $this->discover(['Clean']);
        Gate::policy(Ticket::class, HandWrittenTicketPolicy::class);
        Gate::policy(TicketReply::class, HandWrittenTicketPolicy::class);
        [, $before] = $this->checkJson();

        $this->artisan('access:check')->assertOk()->run();
        [, $after] = $this->checkJson();

        $this->assertSame($before, $after);
        $this->assertCount(2, $after['notes']);
    }

    #[Test]
    public function it_shows_the_policy_of_each_control_in_its_four_forms(): void
    {
        $this->discover(['Clean']);

        [, $none] = $this->checkJson();
        Gate::policy(Ticket::class, TicketPolicyNamingReplies::class);
        Gate::policy(TicketReply::class, HandWrittenTicketPolicy::class);
        [, $declared] = $this->checkJson();

        $this->assertSame([['policy' => null, 'form' => 'none'], ['policy' => null, 'form' => 'none']], array_column($none['controls'], 'policy'));
        $this->assertSame(
            [['policy' => TicketPolicyNamingReplies::class, 'form' => 'controlled'], ['policy' => HandWrittenTicketPolicy::class, 'form' => 'own']],
            array_column($declared['controls'], 'policy'),
        );
        $this->artisan('access:check')
            ->expectsOutputToContain(TicketPolicyNamingReplies::class.', controlled')
            ->expectsOutputToContain(HandWrittenTicketPolicy::class.', own')
            ->run();
    }

    #[Test]
    public function it_shows_an_automatic_policy(): void
    {
        $this->discover(['Clean']);
        Access::registerPolicies();

        [, $report] = $this->checkJson();

        $this->assertSame(['policy' => AutomaticPolicy::class, 'form' => 'automatic'], $report['controls'][0]['policy']);
        $this->artisan('access:check')
            ->expectsOutputToContain(AutomaticPolicy::class.', automatic')
            ->assertOk();
    }

    #[Test]
    public function it_prints_how_many_models_were_scanned_and_where(): void
    {
        $this->discover(['Clean']);
        Access::registerPolicies();

        $this->artisan('access:check')
            ->expectsOutputToContain('0 scanned in app/Models')
            ->assertOk();
        $this->artisan('access:check', ['--models' => [$this->fixture('Clean/Models')]])
            ->expectsOutputToContain('2 scanned in '.$this->fixture('Clean/Models'))
            ->assertOk();

        [, $report] = $this->checkJson(['--models' => [$this->fixture('Clean/Models')]]);

        $this->assertSame(2, $report['discovery']['scanned']);
    }

    #[Test]
    public function it_honours_a_glob_in_the_model_directories(): void
    {
        $this->discover(['Clean']);
        Access::registerPolicies();

        [$code, $report] = $this->checkJson(['--models' => [$this->fixture('Unco*/Models')]]);

        $this->assertSame(1, $code);
        $this->assertSame(1, $report['discovery']['scanned']);
        $this->assertSame([[
            'kind' => 'uncontrolled_model',
            'subject' => Orphan::class,
            'message' => (new UncontrolledModel(Orphan::class))->message(),
        ]], $report['problems']);
    }
}
