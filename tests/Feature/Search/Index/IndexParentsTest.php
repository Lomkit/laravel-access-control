<?php

namespace Lomkit\Access\Tests\Feature\Search\Index;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\PerimeterWithoutSearchKeysException;
use Lomkit\Access\Exceptions\UnsearchableThroughParentException;
use Lomkit\Access\Perimeters\ThroughParent;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\Index\ConfiguredGuideControl;
use Lomkit\Access\Tests\Fixtures\Index\ConfiguredGuideNoteControl;
use Lomkit\Access\Tests\Fixtures\Index\Guide;
use Lomkit\Access\Tests\Fixtures\Index\GuideNote;
use Lomkit\Access\Tests\Fixtures\Index\GuideNoteControl;
use PHPUnit\Framework\Attributes\Test;

class IndexParentsTest extends IndexKeysTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Onboarding' => [1, 2], 'Billing' => [1], 'Pricing' => [2], 'Orphan' => [1]] as $title => $brands) {
            $guide = Guide::query()->where('title', $title)->firstOrFail();

            foreach ($brands as $brand) {
                GuideNote::query()->create(['guide_id' => $guide->getKey(), 'brand_id' => $brand, 'body' => "{$title} in brand {$brand}"]);
            }
        }
    }

    #[Test]
    public function it_agrees_with_the_controlled_list_through_a_parent(): void
    {
        $this->assertIndexKeysAgree(GuideNoteControl::class, $this->readers());
        $this->assertSame(['Onboarding in brand 1', 'Onboarding in brand 2'], $this->notesFoundBy($this->grace));

        $this->brand = 2;

        $this->assertIndexKeysAgree(GuideNoteControl::class, $this->readers());
        $this->assertSame(['Pricing in brand 2'], $this->notesFoundBy($this->grace));
    }

    #[Test]
    public function it_agrees_with_the_controlled_list_through_a_parent_decided_in_the_context_of_the_row(): void
    {
        $this->brand = null;
        Access::rowContextUsing(
            values: fn (): array => $this->brand === null ? [1, 2] : [$this->brand],
            within: function (int|string $brand, Closure $decide): mixed {
                $requested = $this->brand;
                $this->brand = (int) $brand;

                try {
                    return $decide();
                } finally {
                    $this->brand = $requested;
                }
            },
        );
        ConfiguredGuideNoteControl::bind(ThroughParent::belongsTo('guide')->decidedInRowContext('brand_id'));

        $this->assertIndexKeysAgree(GuideNoteControl::class, $this->readers());
        $this->assertSame(['Onboarding in brand 1', 'Pricing in brand 2'], $this->notesFoundBy($this->grace));
        $this->assertSame(['Billing in brand 1', 'Onboarding in brand 1', 'Pricing in brand 2'], $this->notesFoundBy($this->root));
    }

    #[Test]
    public function it_refuses_a_parent_that_delegates_view_to_another_ability(): void
    {
        ConfiguredGuideNoteControl::bind(ThroughParent::belongsTo('guide')->delegates(['view' => 'update']));
        $message = sprintf('of the [%s] control through the [guide] relation delegates view to the [update] ability', ConfiguredGuideNoteControl::class);

        $this->assertThrows(fn () => Access::readerKeysFor(GuideNote::class, $this->grace), UnsearchableThroughParentException::class, $message);
        $this->assertThrows(fn () => Access::readerKeysFor(GuideNote::class, null), UnsearchableThroughParentException::class, $message);
        $this->assertThrows(fn () => Access::searchKeysOf(GuideNote::query()->get()), UnsearchableThroughParentException::class, $message);
    }

    #[Test]
    public function it_refuses_a_parent_listed_across_brands_that_delegates_view_to_update(): void
    {
        ConfiguredGuideControl::bindWithin(Boundary::none(), $this->brandGuides());
        ConfiguredGuideNoteControl::bind(ThroughParent::belongsTo('guide')->delegates(['view' => 'update']));

        $this->assertThrows(fn () => Access::readerKeysFor(GuideNote::class, $this->grace), UnsearchableThroughParentException::class);
        $this->assertThrows(fn () => Access::searchKeysOf(GuideNote::query()->get()), UnsearchableThroughParentException::class);
    }

    #[Test]
    public function it_refuses_a_parent_whose_boundary_has_no_search_form(): void
    {
        ConfiguredGuideControl::bindWithin(new WorkspaceBoundary, $this->clientGuides());
        $message = sprintf('inherits the access of the [%s] control, whose boundary has no search form', ConfiguredGuideControl::class);

        $this->assertThrows(fn () => Access::readerKeysFor(GuideNote::class, $this->grace), UnsearchableThroughParentException::class, $message);
        $this->assertThrows(fn () => Access::searchKeysOf(GuideNote::query()->get()), UnsearchableThroughParentException::class, $message);
    }

    #[Test]
    public function it_refuses_a_parent_with_a_perimeter_without_search_keys(): void
    {
        ConfiguredGuideControl::bind(
            GlobalPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} every guide"))
                ->unrestricted(),
            ClientPerimeter::new()
                ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client guides"))
                ->query(fn (Builder $query, User $user): Builder => $query->where('client_id', $user->client_id)),
        );
        $message = sprintf('perimeter at #2 of the [%s] control declares no search keys', ConfiguredGuideControl::class);

        $this->assertThrows(fn () => Access::readerKeysFor(GuideNote::class, $this->grace), PerimeterWithoutSearchKeysException::class, $message);
        $this->assertThrows(fn () => Access::searchKeysOf(GuideNote::query()->get()), PerimeterWithoutSearchKeysException::class, $message);
    }

    #[Test]
    public function it_checks_the_parents_of_a_parent(): void
    {
        ConfiguredGuideControl::bindWithin(Boundary::none(), $this->clientGuides(), ThroughParent::belongsTo('author')->delegates(['view' => 'update']));
        $message = sprintf('of the [%s] control through the [author] relation delegates view to the [update] ability', ConfiguredGuideControl::class);

        $this->assertThrows(fn () => Access::readerKeysFor(GuideNote::class, $this->grace), UnsearchableThroughParentException::class, $message);
        $this->assertThrows(fn () => Access::searchKeysOf(GuideNote::query()->get()), UnsearchableThroughParentException::class, $message);
    }

    protected function clientGuides(): ClientPerimeter
    {
        return ClientPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $user->can("{$ability} client guides"))
            ->query(fn (Builder $query, User $user): Builder => $query->where('client_id', $user->client_id))
            ->searchKeys(
                name: 'client',
                row: fn (Guide $guide): mixed => $guide->getAttribute('client_id'),
                reader: fn (User $user): mixed => $user->client_id,
            );
    }

    protected function brandGuides(): ClientPerimeter
    {
        return ClientPerimeter::new()
            ->allowed(fn (User $user, Ability $ability): bool => $this->brandsWhere($user, "{$ability} brand guides") !== [])
            ->query(fn (Builder $query, User $user, Ability $ability): Builder => $query->whereIn('brand_id', $this->brandsWhere($user, "{$ability} brand guides")))
            ->searchKeys(
                name: 'brand',
                row: fn (Guide $guide): mixed => $guide->getAttribute('brand_id'),
                reader: fn (User $user): array => $this->brandsWhere($user, 'view brand guides'),
            );
    }

    /** @return list<int> */
    protected function brandsWhere(User $user, string $permission): array
    {
        $rights = [
            'view brand guides' => ['Grace' => [1, 2]],
            'update brand guides' => ['Grace' => [1]],
        ];

        return $rights[$permission][$user->name] ?? [];
    }

    /** @return list<string> */
    protected function notesFoundBy(User $reader): array
    {
        $rows = GuideNote::query()->orderBy('id')->get();
        $bodies = $rows->pluck('body', 'id')->all();
        $values = Access::readerKeysFor(GuideNote::class, $reader)->values();
        $found = [];

        foreach (Access::searchKeysOf($rows) as $key => $document) {
            if (array_intersect($document['_access'], $values['_access']) !== [] && array_intersect($document['_boundary'], $values['_boundary']) !== []) {
                $found[] = $bodies[$key];
            }
        }

        sort($found);

        return $found;
    }
}
