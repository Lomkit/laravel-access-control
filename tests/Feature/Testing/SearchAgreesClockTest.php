<?php

namespace Lomkit\Access\Tests\Feature\Testing;

use Carbon\Carbon as BaseCarbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use LogicException;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\AssignedPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\Assignment;
use Lomkit\Access\Tests\Fixtures\Search\ClockedWorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SearchAgreesClockTest extends SearchTestCase
{
    protected User $ada;

    protected User $grace;

    protected User $hank;

    protected Article $primer;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user('Ada');
        $this->grace = $this->user('Grace');
        $this->hank = $this->user('Hank');

        Article::withoutSyncingToSearch(function (): void {
            $this->primer = Article::factory()->createOne(['title' => 'Scout primer']);
            $recipes = Article::factory()->createOne(['title' => 'Scout recipes']);
            $internals = Article::factory()->createOne(['title' => 'Scout internals']);
            Article::factory()->createOne(['title' => 'Scout orphan']);

            $this->assign($this->primer, $this->ada, '2026-03-01 10:00:00', '2026-03-31 18:00:00');
            $this->assign($recipes, $this->grace, '2026-04-10 09:00:00', null);
            $this->assign($internals, $this->ada, '2026-01-01 00:00:00', '2026-01-31 23:59:59');
            $this->assign($internals, $this->hank, '2026-03-20 08:00:00', '2026-03-25 17:00:00');
        });
    }

    #[\Override]
    protected function tearDown(): void
    {
        Date::useDefault();

        parent::tearDown();
    }

    protected function assign(Article $article, User $user, string $startsAt, ?string $endsAt): void
    {
        Assignment::query()->create([
            'article_id' => $article->getKey(),
            'user_id' => $user->getKey(),
            'starts_at' => Carbon::parse($startsAt),
            'ends_at' => $endsAt === null ? null : Carbon::parse($endsAt),
        ]);
    }

    protected function assigned(): AssignedPerimeter
    {
        return AssignedPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->whereHas('assignments', fn (Builder $assignments) => $this->active($assignments->where('user_id', $user->getKey())));
            });
    }

    protected function bindTheRecipe(): void
    {
        ConfiguredArticleControl::bind(
            $this->assigned()->searchKeys(
                name: 'assigned',
                row: fn (Article $article): mixed => $article->getKey(),
                reader: fn (User $user): array => $this->active(Assignment::query()->where('user_id', $user->getKey()))
                    ->pluck('article_id')
                    ->all(),
            ),
        );
    }

    protected function bindTheClockInTheRow(string $clock = 'now'): void
    {
        ConfiguredArticleControl::bind(
            $this->assigned()->searchKeys(
                name: 'assigned',
                row: fn (Article $article): array => $article->assignments
                    ->filter(fn (Assignment $assignment): bool => $this->isActive($assignment, self::instantOf($clock)))
                    ->pluck('user_id')
                    ->all(),
                reader: fn (User $user): mixed => $user->getKey(),
                load: ['assignments'],
            ),
        );
    }

    protected function isActive(Assignment $assignment, CarbonInterface $instant): bool
    {
        return $assignment->starts_at <= $instant && ($assignment->ends_at === null || $assignment->ends_at >= $instant);
    }

    protected static function instantOf(string $clock): CarbonInterface
    {
        return match ($clock) {
            'now' => now(),
            'Date::now' => Date::now(),
            'Illuminate Carbon::now' => Carbon::now(),
            'Carbon::now' => BaseCarbon::now(),
            'CarbonImmutable::now' => CarbonImmutable::now(),
        };
    }

    /** @return array<string, array{string}> */
    public static function clocks(): array
    {
        return [
            'now()' => ['now'],
            'Date::now()' => ['Date::now'],
            'Illuminate\\Support\\Carbon::now()' => ['Illuminate Carbon::now'],
            'Carbon\\Carbon::now()' => ['Carbon::now'],
            'Carbon\\CarbonImmutable::now()' => ['CarbonImmutable::now'],
        ];
    }

    protected function active(Builder $assignments): Builder
    {
        return $assignments
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $end) => $end->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    protected function clockMessage(string $direction): string
    {
        return sprintf(
            'The keys of the [%s] control differ between two computations: the [_access] keys of the [%s] row with key [%s] are not the same when they are computed again with the clock moved 400 days %s, in the keys of the rules [assigned]. The keys of a row are computed when it is indexed and stay in the index until it is indexed again, so a row: closure must give the same keys at any instant. Read the clock in the reader: closure of searchKeys(), never in row:, and make row: give the same keys every time, such as by ordering a relation it reads with first(). See "Rights that depend on the date" at https://github.com/Lomkit/laravel-access-control/blob/1.x/UPGRADE.md#rights-that-depend-on-the-date.',
            ArticleControl::class,
            Article::class,
            $this->primer->getKey(),
            $direction,
        );
    }

    /** @return list<string> */
    protected function foundBy(User $reader): array
    {
        return Article::search('Scout')
            ->controlledFor($reader)
            ->get()
            ->pluck('title')
            ->sort()
            ->values()
            ->all();
    }

    /** @return list<string> */
    protected function listedFor(User $reader): array
    {
        return Article::query()
            ->controlledFor($reader)
            ->pluck('title')
            ->sort()
            ->values()
            ->all();
    }

    protected function failureOf(string $assertion, User $reader): string
    {
        try {
            $this->{$assertion}(ArticleControl::class, [$reader]);
        } catch (AssertionFailedError $failure) {
            return $failure->getMessage();
        }

        return "{$assertion}() passed";
    }

    #[Test]
    public function it_fails_when_a_row_key_reads_the_clock(): void
    {
        $this->bindTheClockInTheRow();
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($this->clockMessage('back'));

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace, $this->hank]);
    }

    #[Test]
    public function it_fails_the_index_keys_when_a_row_key_reads_the_clock(): void
    {
        $this->bindTheClockInTheRow();
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($this->clockMessage('back'));

        $this->assertIndexKeysAgree(ArticleControl::class, readers: [null, $this->ada, $this->grace, $this->hank]);
    }

    #[Test]
    public function it_fails_when_the_clock_moving_forward_changes_a_row_key(): void
    {
        $this->bindTheClockInTheRow();
        $this->travelTo(Carbon::parse('2025-02-01 12:00:00'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($this->clockMessage('forward'));

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
    }

    #[Test]
    public function it_fails_when_a_loaded_relation_reads_the_clock(): void
    {
        ConfiguredArticleControl::bind(
            $this->assigned()->searchKeys(
                name: 'assigned',
                row: fn (Article $article): array => $article->activeAssignments->pluck('user_id')->all(),
                reader: fn (User $user): mixed => $user->getKey(),
                load: ['activeAssignments'],
            ),
        );
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($this->clockMessage('back'));

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
    }

    #[Test]
    public function it_passes_the_recipe_before_during_and_after_each_period(): void
    {
        $this->bindTheRecipe();
        $this->reindex();
        $expected = [
            '2026-02-28 12:00:00' => [[], [], []],
            '2026-03-01 09:59:59' => [[], [], []],
            '2026-03-01 10:00:00' => [['Scout primer'], [], []],
            '2026-03-20 07:59:59' => [['Scout primer'], [], []],
            '2026-03-20 08:00:00' => [['Scout primer'], [], ['Scout internals']],
            '2026-03-25 17:00:00' => [['Scout primer'], [], ['Scout internals']],
            '2026-03-25 17:00:01' => [['Scout primer'], [], []],
            '2026-03-31 18:00:00' => [['Scout primer'], [], []],
            '2026-03-31 18:00:01' => [[], [], []],
            '2026-04-10 08:59:59' => [[], [], []],
            '2026-04-10 09:00:00' => [[], ['Scout recipes'], []],
            '2031-06-01 12:00:00' => [[], ['Scout recipes'], []],
        ];

        foreach ($expected as $instant => [$ada, $grace, $hank]) {
            $this->travelTo(Carbon::parse($instant));

            $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace, $this->hank]);
            $this->assertIndexKeysAgree(ArticleControl::class, readers: [null, $this->ada, $this->grace, $this->hank]);
            $this->assertSame([$ada, $grace, $hank], [$this->listedFor($this->ada), $this->listedFor($this->grace), $this->listedFor($this->hank)], $instant);
            $this->assertSame([$ada, $grace, $hank], [$this->foundBy($this->ada), $this->foundBy($this->grace), $this->foundBy($this->hank)], $instant);
        }
    }

    #[Test]
    public function it_keeps_the_instant_of_the_test_after_a_pass(): void
    {
        $this->bindTheRecipe();
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
        $this->assertIndexKeysAgree(ArticleControl::class, readers: [$this->ada]);

        $this->assertSame('2026-03-15 12:00:00', Date::now()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_keeps_the_instant_of_the_test_after_a_failure(): void
    {
        $this->bindTheClockInTheRow();
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        foreach (['assertSearchAgrees', 'assertIndexKeysAgree'] as $assertion) {
            $this->assertStringContainsString('control differ between two computations', $this->failureOf($assertion, $this->ada));

            $this->assertSame('2026-03-15 12:00:00', Date::now()->format('Y-m-d H:i:s'));
        }
    }

    #[Test]
    public function it_keeps_the_instant_of_the_test_when_a_row_key_throws_under_the_moved_clock(): void
    {
        ConfiguredArticleControl::bind(
            $this->assigned()->searchKeys(
                name: 'assigned',
                row: fn (Article $article): mixed => now()->year > 2026 ? throw new LogicException('The clock moved.') : $article->getKey(),
                reader: fn (User $user): array => [],
            ),
        );
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        $this->assertThrows(fn () => $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]), LogicException::class, 'The clock moved.');
        $this->assertSame('2026-03-15 12:00:00', Date::now()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_leaves_the_clock_running_after_a_pass_without_a_frozen_instant(): void
    {
        $this->bindTheRecipe();
        $this->assertFalse(Date::hasTestNow());

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
        $this->assertIndexKeysAgree(ArticleControl::class, readers: [$this->ada]);

        $this->assertFalse(Date::hasTestNow());
    }

    #[Test]
    public function it_leaves_the_clock_running_after_a_failure_without_a_frozen_instant(): void
    {
        $this->bindTheClockInTheRow();
        $this->assign($this->primer, $this->hank, now()->subDay()->format('Y-m-d H:i:s'), now()->addDay()->format('Y-m-d H:i:s'));
        $this->assertFalse(Date::hasTestNow());

        foreach (['assertSearchAgrees', 'assertIndexKeysAgree'] as $assertion) {
            $this->assertStringContainsString('control differ between two computations', $this->failureOf($assertion, $this->hank));

            $this->assertFalse(Date::hasTestNow());
        }
    }

    #[Test]
    #[DataProvider('clocks')]
    public function it_fails_on_each_way_of_reading_the_clock_of_laravel(string $clock): void
    {
        $this->bindTheClockInTheRow($clock);
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($this->clockMessage('back'));

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
    }

    #[Test]
    #[DataProvider('clocks')]
    public function it_fails_on_each_way_of_reading_the_clock_under_a_date_factory(string $clock): void
    {
        Date::useFactory(new Factory([], Carbon::class));
        $this->bindTheClockInTheRow($clock);
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($this->clockMessage('back'));

        $this->assertIndexKeysAgree(ArticleControl::class, readers: [$this->ada]);
    }

    #[Test]
    #[DataProvider('clocks')]
    public function it_fails_on_each_way_of_reading_the_clock_under_a_date_class(string $clock): void
    {
        Date::use(CarbonImmutable::class);
        $this->bindTheClockInTheRow($clock);
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($this->clockMessage('back'));

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
    }

    #[Test]
    public function it_lets_the_test_travel_again_under_a_date_factory(): void
    {
        Date::useFactory(new Factory([], Carbon::class));
        $this->bindTheRecipe();
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
        $this->assertIndexKeysAgree(ArticleControl::class, readers: [$this->ada]);
        $this->travelTo(Carbon::parse('2026-05-01 08:00:00'));

        $this->assertSame('2026-05-01 08:00:00', now()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-01 08:00:00', Date::now()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_checks_the_clock_under_a_date_callable(): void
    {
        Date::useCallable(fn (CarbonInterface $date): CarbonInterface => $date);
        $this->bindTheRecipe();
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
        $this->assertIndexKeysAgree(ArticleControl::class, readers: [$this->ada]);
        $this->assertSame('2026-03-15 12:00:00', now()->format('Y-m-d H:i:s'));

        $this->bindTheClockInTheRow('Date::now');

        $this->assertStringContainsString($this->clockMessage('back'), $this->failureOf('assertSearchAgrees', $this->ada));
        $this->assertSame('2026-03-15 12:00:00', now()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_fails_when_a_boundary_key_reads_the_clock(): void
    {
        ConfiguredArticleControl::bindWithin(new ClockedWorkspaceBoundary, $this->assigned()->searchKeys(
            name: 'assigned',
            row: fn (Article $article): mixed => $article->getKey(),
            reader: fn (User $user): array => [],
        ));
        $this->travelTo(Carbon::parse('2026-03-15 12:00:00'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches(sprintf(
            '/^The keys of the \[%s\] control differ between two computations: the \[_boundary\] keys of the \[%s\] row with key \[\d+\] are not the same when they are computed again with the clock moved 400 days back, in the keys of the rules \[workspace\]\./',
            preg_quote(ArticleControl::class, '/'),
            preg_quote(Article::class, '/'),
        ));

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada]);
    }

    #[Test]
    public function it_leaves_the_clock_running_when_a_row_key_throws_under_the_moved_clock(): void
    {
        $year = now()->year;
        ConfiguredArticleControl::bind(
            $this->assigned()->searchKeys(
                name: 'assigned',
                row: fn (Article $article): mixed => now()->year > $year ? throw new LogicException('The clock moved.') : $article->getKey(),
                reader: fn (User $user): array => [],
            ),
        );

        $this->assertThrows(fn () => $this->assertIndexKeysAgree(ArticleControl::class, readers: [$this->ada]), LogicException::class, 'The clock moved.');
        $this->assertFalse(Carbon::hasTestNow());
    }
}
