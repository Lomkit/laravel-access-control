<?php

namespace Lomkit\Access\Tests\Feature\Search\Index;

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\InvalidIndexRowException;
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
use Lomkit\Access\Tests\Fixtures\Index\TicketControl;
use PHPUnit\Framework\Attributes\Test;

class IndexKeysRefusalTest extends IndexKeysTestCase
{
    #[Test]
    public function it_refuses_rows_of_two_models(): void
    {
        $this->expectException(InvalidIndexRowException::class);
        $this->expectExceptionMessage(sprintf('rows of the [%s] model and of the [%s] model', Guide::class, Ticket::class));

        Access::indexKeysOf([Guide::query()->firstOrFail(), Ticket::query()->firstOrFail()]);
    }

    #[Test]
    public function it_refuses_a_value_that_is_not_a_model(): void
    {
        $this->expectException(InvalidIndexRowException::class);
        $this->expectExceptionMessage('received a value of type [string]');

        Access::indexKeysOf([Guide::query()->firstOrFail(), 'Onboarding']);
    }

    #[Test]
    public function it_refuses_an_unsaved_row(): void
    {
        $this->expectException(InvalidIndexRowException::class);
        $this->expectExceptionMessage(sprintf('an unsaved or deleted [%s] row', Guide::class));

        Access::indexKeysOf([new Guide(['title' => 'Draft', 'brand_id' => 1])]);
    }

    #[Test]
    public function it_refuses_a_force_deleted_row(): void
    {
        $guide = Guide::query()->firstOrFail();
        $guide->forceDelete();

        $this->assertThrows(fn () => Access::indexKeysOf([$guide]), InvalidIndexRowException::class, sprintf('an unsaved or deleted [%s] row', Guide::class));
    }

    #[Test]
    public function it_refuses_a_soft_deleted_row_that_the_controlled_list_never_shows(): void
    {
        $ticket = Ticket::query()->where('title', 'Login fails')->firstOrFail();
        $ticket->delete();

        $this->assertThrows(fn () => Access::indexKeysOf([$ticket]), InvalidIndexRowException::class, sprintf('a soft-deleted [%s] row, which controlledFor() never lists', Ticket::class));
        $this->assertThrows(fn () => Access::indexKeysOf(Ticket::withTrashed()->get()), InvalidIndexRowException::class);
        $this->assertCount(2, Access::indexKeysOf(Ticket::query()->get()));
        $this->assertIndexKeysAgree(TicketControl::class, $this->readers());
    }

    #[Test]
    public function it_refuses_a_row_whose_key_is_not_an_int_or_a_string(): void
    {
        $guide = new Guide(['id' => null, 'title' => 'Keyless', 'deleted_at' => null]);
        $guide->exists = true;

        $this->expectException(InvalidIndexRowException::class);
        $this->expectExceptionMessage(sprintf('a [%s] row whose key is of type [null]', Guide::class));

        Access::indexKeysOf([$guide]);
    }

    #[Test]
    public function it_refuses_a_model_without_a_control(): void
    {
        $this->assertThrows(fn () => Access::indexReaderKeys($this->ada, Client::class), MissingControlException::class);
        $this->assertThrows(fn () => Access::indexKeysOf([$this->acme]), MissingControlException::class);
    }

    #[Test]
    public function it_refuses_a_boundary_without_a_search_form(): void
    {
        $mission = new Mission(['id' => 1]);
        $mission->exists = true;

        $this->assertThrows(fn () => Access::indexReaderKeys(null, Mission::class), UnsearchableBoundaryException::class);
        $this->assertThrows(fn () => Access::indexKeysOf([$mission]), UnsearchableBoundaryException::class);
    }

    #[Test]
    public function it_refuses_a_perimeter_without_search_keys_for_every_reader(): void
    {
        $post = new Post(['id' => 1, 'workspace_id' => 1]);
        $post->exists = true;
        $message = sprintf('perimeter at #1.2 of the [%s] control declares no search keys', PostControl::class);

        $this->assertThrows(fn () => Access::indexReaderKeys(null, Post::class), PerimeterWithoutSearchKeysException::class, $message);
        $this->assertThrows(fn () => Access::indexReaderKeys($this->ada, Post::class), PerimeterWithoutSearchKeysException::class, $message);
        $this->assertThrows(fn () => Access::indexKeysOf([$post]), PerimeterWithoutSearchKeysException::class, $message);
    }

    #[Test]
    public function it_refuses_a_model_whose_morph_class_names_another_model(): void
    {
        $pinned = PinnedGuide::query()->firstOrFail();
        $message = sprintf('The [%s] model returns the morph class [%s], which names the [%s] model', PinnedGuide::class, Guide::class, Guide::class);

        $this->assertThrows(fn () => Access::indexReaderKeys($this->root, PinnedGuide::class), SharedMorphClassException::class, $message);
        $this->assertThrows(fn () => Access::indexKeysOf([$pinned]), SharedMorphClassException::class, $message);

        Relation::morphMap(['guide' => Guide::class]);

        $this->assertThrows(fn () => Access::indexReaderKeys($this->root, PinnedGuide::class), SharedMorphClassException::class, sprintf('returns the morph class [guide], which names the [%s] model', Guide::class));
        $this->assertFalse(Access::indexReaderKeys($this->root, Guide::class)->isClosed());
    }

    #[Test]
    public function it_lets_laravel_refuse_a_model_missing_from_a_required_morph_map(): void
    {
        Relation::requireMorphMap();

        $this->assertThrows(fn () => Access::indexReaderKeys($this->root, Guide::class), ClassMorphViolationException::class);
        $this->assertThrows(fn () => Access::indexKeysOf(Guide::query()->get()), ClassMorphViolationException::class);

        Relation::morphMap(['guide' => Guide::class, 'user' => User::class]);

        $this->assertFalse(Access::indexReaderKeys($this->root, Guide::class)->isClosed());
    }

    #[Test]
    public function it_refuses_an_ability(): void
    {
        $this->expectException(UnsearchableAbilityException::class);
        $this->expectExceptionMessage(sprintf('Access::indexReaderKeys() for the [%s] model received an argument after the model', Guide::class));

        Access::indexReaderKeys($this->ada, Guide::class, 'update');
    }

    #[Test]
    public function it_refuses_an_ability_in_the_agreement(): void
    {
        $this->expectException(UnsearchableAbilityException::class);
        $this->expectExceptionMessage(sprintf('assertIndexKeysAgree() for the [%s] control received an argument after the readers', GuideControl::class));

        $this->assertIndexKeysAgree(GuideControl::class, [$this->ada], 'update');
    }
}
