<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\DuplicateSearchKeyNameException;
use Lomkit\Access\Exceptions\InvalidSearchKeyNameException;
use Lomkit\Access\Exceptions\UnrestrictedPerimeterCallbackException;
use Lomkit\Access\Perimeters\Levels;
use Lomkit\Access\Perimeters\Perimeter;
use Lomkit\Access\Search\SearchKeys;
use Lomkit\Access\Tests\Fixtures\Agencies\Member;
use Lomkit\Access\Tests\Fixtures\Blog\Blog;
use Lomkit\Access\Tests\Fixtures\Blog\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredPostControl;
use Lomkit\Access\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SearchKeysDeclarationTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Blog];
    }

    protected function own(string $name = 'author'): OwnPerimeter
    {
        return OwnPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->query(function (Builder $query, User $user): void {
                $query->whereBelongsTo($user, 'author');
            })
            ->searchKeys($name, fn (Post $post): mixed => $post->author_id, fn (User $user): mixed => $user->getKey());
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return [
            'uppercase' => ['Client'],
            'a dash' => ['client-id'],
            'a dot' => ['client.id'],
            'an accent' => ['clé'],
            'a space' => ['client id'],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function it_refuses_a_name_that_is_not_made_of_lowercase_letters_digits_and_underscores(string $name): void
    {
        $this->expectException(InvalidSearchKeyNameException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter declares search keys with the invalid name [%s].', OwnPerimeter::class, $name));

        OwnPerimeter::new()->searchKeys($name, fn (Post $post): int => 1, fn (User $user): int => 1);
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function it_refuses_such_a_name_for_the_search_keys_of_a_boundary(string $name): void
    {
        $this->expectException(InvalidSearchKeyNameException::class);
        $this->expectExceptionMessage(sprintf('The search key name [%s] is invalid.', $name));

        SearchKeys::of($name, fn (Post $post): int => 1, fn (User $user): int => 1);
    }

    #[Test]
    public function it_accepts_names_made_of_lowercase_letters_digits_and_underscores(): void
    {
        foreach (['client', 'shared_with', '_draft', '2fa'] as $name) {
            $this->assertSame($name, OwnPerimeter::new()->searchKeys($name, fn (Post $post): int => 1, fn (User $user): int => 1)->declaredSearchKeys()?->name());
        }
    }

    #[Test]
    public function it_refuses_search_keys_on_an_unrestricted_perimeter(): void
    {
        $this->expectException(UnrestrictedPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter is unrestricted and declares a [searchKeys] callback.', GlobalPerimeter::class));

        GlobalPerimeter::new()->unrestricted()->searchKeys('global', fn (Post $post): int => 1, fn (User $user): int => 1);
    }

    #[Test]
    public function it_refuses_to_make_a_perimeter_with_search_keys_unrestricted(): void
    {
        $this->expectException(UnrestrictedPerimeterCallbackException::class);
        $this->expectExceptionMessage(sprintf('The [%s] perimeter is unrestricted and declares a [searchKeys] callback.', GlobalPerimeter::class));

        GlobalPerimeter::new()->searchKeys('global', fn (Post $post): int => 1, fn (User $user): int => 1)->unrestricted();
    }

    #[Test]
    public function it_refuses_two_perimeters_of_a_control_with_the_same_name(): void
    {
        $control = ConfiguredPostControl::bind(
            Levels::of($this->own('author')),
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('author', fn (Post $post): array => [], fn (User $user): mixed => $user->getKey()),
        );

        $this->expectException(DuplicateSearchKeyNameException::class);
        $this->expectExceptionMessage(sprintf('Two perimeters of the [%s] control declare search keys named [author].', $control::class));

        Access::control(PostControl::class);
    }

    #[Test]
    public function it_compares_the_search_keys_in_the_shape_of_a_perimeter(): void
    {
        $declare = fn (string $name, int $value): Perimeter => OwnPerimeter::new()->searchKeys($name, fn (Post $post): int => $value, fn (User $user): int => 1);

        $this->assertTrue($declare('author', 1)->shape()->matches($declare('author', 1)->shape()));
        $this->assertFalse($declare('author', 1)->shape()->matches($declare('editor', 1)->shape()));
        $this->assertFalse($declare('author', 1)->shape()->matches($declare('author', 2)->shape()));
        $this->assertFalse($declare('author', 1)->shape()->matches(OwnPerimeter::new()->shape()));
    }

    #[Test]
    public function it_skips_a_perimeter_whose_reader_keys_are_typed_for_another_reader(): void
    {
        $ada = User::factory()->createOne(['name' => 'Ada', 'email' => 'ada@example.com']);
        Post::factory()->for($ada, 'author')->createOne(['title' => 'Release notes']);
        ConfiguredPostControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->query(function (Builder $query, User $user): void {
                    $query->whereBelongsTo($user, 'author');
                })
                ->searchKeys('author', fn (Post $post): mixed => $post->author_id, fn (Member $member): mixed => $member->getKey()),
        );

        $this->assertSame([], Post::controlledFor($ada)->pluck('title')->all());
    }

    #[Test]
    public function it_lists_the_attributes_the_search_engine_filters_on(): void
    {
        $this->assertSame(['_access', '_boundary'], Access::searchFilterables());
    }
}
