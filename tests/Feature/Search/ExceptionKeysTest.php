<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Access;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Lomkit\Access\Tests\Fixtures\Search\SectorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\StandInPerimeter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ExceptionKeysTest extends SearchTestCase
{
    private const array SECTORS = [1, 2, 3];

    protected User $ada;

    protected User $grace;

    protected User $linus;

    /** @var list<int> */
    protected array $excludedSectors = [2, 3];

    /** @var list<int> */
    protected array $managedSectors = [3];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user('Ada');
        $this->grace = $this->user('Grace');
        $this->linus = $this->user('Linus');
        Article::withoutSyncingToSearch(function (): void {
            Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'sector_id' => 1, 'title' => 'Scout primer']);
            Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'sector_id' => 2, 'title' => 'Scout recipes']);
            Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'sector_id' => 3, 'title' => 'Scout tuning']);
            Article::factory()->createOne(['author_id' => $this->linus->getKey(), 'sector_id' => 1, 'title' => 'Scout internals']);
            Article::factory()->createOne(['author_id' => $this->linus->getKey(), 'sector_id' => 3, 'title' => 'Scout archive']);
        });
        ConfiguredArticleControl::bind($this->standIns(), $this->sectors());
        $this->reindex();
        $this->actingAs($this->grace);
    }

    #[Test]
    public function it_hides_the_excluded_sectors_of_a_stand_in_with_the_complement_of_its_exclusions(): void
    {
        $this->managedSectors = [];

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace, $this->linus]);
        $this->assertSame(['Scout primer'], $this->searchedTitles());
    }

    #[Test]
    public function it_finds_an_excluded_row_that_another_perimeter_grants(): void
    {
        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->ada, $this->grace, $this->linus]);
        $this->assertSame(['Scout archive', 'Scout primer', 'Scout tuning'], $this->searchedTitles());
    }

    #[Test]
    public function it_follows_a_changed_exclusion_without_reindexing(): void
    {
        $this->excludedSectors = [3];
        Access::forgetDecisions();

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->grace]);
        $this->assertSame(['Scout archive', 'Scout primer', 'Scout recipes', 'Scout tuning'], $this->searchedTitles());

        $this->excludedSectors = [1, 2, 3];
        $this->managedSectors = [];
        Access::forgetDecisions();

        $this->assertSearchAgrees(ArticleControl::class, readers: [$this->grace]);
        $this->assertSame([], $this->searchedTitles());
    }

    private function standIns(): Perimeter
    {
        return StandInPerimeter::new()
            ->allowed(fn (User $user): bool => $this->principalsOf($user) !== [])
            ->query(function (Builder $query, User $user): void {
                $query->where(function (Builder $query) use ($user): void {
                    foreach ($this->principalsOf($user) as $principal) {
                        $query->orWhere(fn (Builder $query): Builder => $query
                            ->where('author_id', $principal)
                            ->whereNotIn('sector_id', $this->excludedSectors));
                    }
                });
            })
            ->searchKeys(
                name: 'stand_in',
                row: fn (Article $article): array => [[$article->author_id, $article->sector_id]],
                reader: fn (User $user): array => array_merge(...array_map(
                    fn (int $principal): array => array_map(
                        fn (int $sector): array => [$principal, $sector],
                        array_values(array_diff(self::SECTORS, $this->excludedSectors)),
                    ),
                    $this->principalsOf($user),
                )),
            );
    }

    private function sectors(): Perimeter
    {
        return SectorPerimeter::new()
            ->allowed(fn (User $user): bool => $user->is($this->grace) && $this->managedSectors !== [])
            ->query(function (Builder $query): void {
                $query->whereIn('sector_id', $this->managedSectors);
            })
            ->searchKeys(
                name: 'sector',
                row: fn (Article $article): mixed => $article->sector_id,
                reader: fn (User $user): array => $this->managedSectors,
            );
    }

    /** @return list<int> */
    private function principalsOf(User $user): array
    {
        return $user->is($this->grace) ? [$this->ada->getKey()] : [];
    }
}
