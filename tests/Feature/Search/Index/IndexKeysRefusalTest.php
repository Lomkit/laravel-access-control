<?php

namespace Lomkit\Access\Tests\Feature\Search\Index;

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidSearchRowException;
use Lomkit\Access\Exceptions\MissingControlException;
use Lomkit\Access\Exceptions\PerimeterWithoutSearchKeysException;
use Lomkit\Access\Exceptions\SharedMorphClassException;
use Lomkit\Access\Exceptions\UnsearchableAbilityException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Tests\Fixtures\Agencies\Mission;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Index\Guide;
use Lomkit\Access\Tests\Fixtures\Index\GuideControl;
use Lomkit\Access\Tests\Fixtures\Index\PinnedGuide;
use Lomkit\Access\Tests\Fixtures\Index\Ticket;
use PHPUnit\Framework\Attributes\Test;

class IndexKeysRefusalTest extends IndexKeysTestCase
{
    #[Test]
    public function it_refuses_rows_of_two_models(): void
    {
        $this->expectException(InvalidSearchRowException::class);
        $this->expectExceptionMessage(sprintf('rows of the [%s] model and of the [%s] model', Guide::class, Ticket::class));

        Access::searchKeysOf([Guide::query()->firstOrFail(), Ticket::query()->firstOrFail()]);
    }

    #[Test]
    public function it_refuses_a_value_that_is_not_a_model(): void
    {
        $this->expectException(InvalidSearchRowException::class);
        $this->expectExceptionMessage('received a value of type [string]');

        Access::searchKeysOf([Guide::query()->firstOrFail(), 'Onboarding']);
    }

    #[Test]
    public function it_refuses_an_unsaved_row(): void
    {
        $this->expectException(InvalidSearchRowException::class);
        $this->expectExceptionMessage(sprintf('an unsaved [%s] row', Guide::class));

        Access::searchKeysOf([new Guide(['title' => 'Draft', 'brand_id' => 1])]);
    }

    #[Test]
    public function it_refuses_a_model_without_a_control(): void
    {
        $this->assertThrows(fn () => Access::readerKeysFor(Client::class, $this->ada), MissingControlException::class);
        $this->assertThrows(fn () => Access::searchKeysOf([$this->acme]), MissingControlException::class);
    }

    #[Test]
    public function it_refuses_a_boundary_without_a_search_form(): void
    {
        $mission = new Mission(['id' => 1]);
        $mission->exists = true;

        $this->assertThrows(fn () => Access::readerKeysFor(Mission::class, null), UnsearchableBoundaryException::class);
        $this->assertThrows(fn () => Access::searchKeysOf([$mission]), UnsearchableBoundaryException::class);
    }

    #[Test]
    public function it_refuses_a_perimeter_without_search_keys_for_every_reader(): void
    {
        $post = new Post(['id' => 1, 'workspace_id' => 1]);
        $post->exists = true;
        $message = sprintf('perimeter at #1.2 of the [%s] control declares no search keys', PostControl::class);

        $this->assertThrows(fn () => Access::readerKeysFor(Post::class, null), PerimeterWithoutSearchKeysException::class, $message);
        $this->assertThrows(fn () => Access::readerKeysFor(Post::class, $this->ada), PerimeterWithoutSearchKeysException::class, $message);
        $this->assertThrows(fn () => Access::searchKeysOf([$post]), PerimeterWithoutSearchKeysException::class, $message);
    }

    #[Test]
    public function it_refuses_a_model_whose_morph_class_names_another_model(): void
    {
        $pinned = PinnedGuide::query()->firstOrFail();
        $message = sprintf('The [%s] model returns the morph class [%s], which names the [%s] model', PinnedGuide::class, Guide::class, Guide::class);

        $this->assertThrows(fn () => Access::readerKeysFor(PinnedGuide::class, $this->root), SharedMorphClassException::class, $message);
        $this->assertThrows(fn () => Access::searchKeysOf([$pinned]), SharedMorphClassException::class, $message);

        Relation::morphMap(['guide' => Guide::class]);

        $this->assertThrows(fn () => Access::readerKeysFor(PinnedGuide::class, $this->root), SharedMorphClassException::class, sprintf('returns the morph class [guide], which names the [%s] model', Guide::class));
        $this->assertFalse(Access::readerKeysFor(Guide::class, $this->root)->isClosed());
    }

    #[Test]
    public function it_lets_laravel_refuse_a_model_missing_from_a_required_morph_map(): void
    {
        Relation::requireMorphMap();

        $this->assertThrows(fn () => Access::readerKeysFor(Guide::class, $this->root), ClassMorphViolationException::class);
        $this->assertThrows(fn () => Access::searchKeysOf(Guide::query()->get()), ClassMorphViolationException::class);

        Relation::morphMap(['guide' => Guide::class, 'user' => User::class]);

        $this->assertFalse(Access::readerKeysFor(Guide::class, $this->root)->isClosed());
    }

    #[Test]
    public function it_refuses_an_ability(): void
    {
        $this->expectException(UnsearchableAbilityException::class);
        $this->expectExceptionMessage(sprintf('Access::readerKeysFor() for the [%s] model received an argument after the reader', Guide::class));

        Access::readerKeysFor(Guide::class, $this->ada, 'update');
    }

    #[Test]
    public function it_refuses_an_ability_in_the_agreement(): void
    {
        $this->expectException(UnsearchableAbilityException::class);
        $this->expectExceptionMessage(sprintf('assertIndexKeysAgree() for the [%s] control received an argument after the readers', GuideControl::class));

        $this->assertIndexKeysAgree(GuideControl::class, [$this->ada], 'update');
    }
}
