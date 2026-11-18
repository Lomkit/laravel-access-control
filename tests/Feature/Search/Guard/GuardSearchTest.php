<?php

namespace Lomkit\Access\Tests\Feature\Search\Guard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\AmbiguousSearchIndexException;
use Lomkit\Access\Exceptions\InvalidSearchBodyException;
use Lomkit\Access\Exceptions\UnguardableSearchEngineException;
use Lomkit\Access\Exceptions\UnknownSearchIndexException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Search\Tokens\Syntax\TypesenseSyntax;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredGuardedArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\SearchGuard\GuardedArticle;
use Lomkit\Access\Tests\Fixtures\SearchGuard\GuardTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[Group('scout')]
class GuardSearchTest extends GuardTestCase
{
    #[\Override]
    protected function engineClass(): string
    {
        return TypesenseEngine::class;
    }

    /** @param  array<string, mixed>  $search */
    protected function guarded(array $search, ?User $reader): mixed
    {
        $body = Access::guardSearch(['searches' => [['collection' => $this->index(), ...$search]]], $reader);

        return $body['searches'][0]['filter_by'] ?? null;
    }

    /** @return array<string, array{array<array-key, mixed>}> */
    public static function shapelessBodies(): array
    {
        return [
            'an empty body' => [[]],
            'a body of another engine' => [['hits' => []]],
            'two lists of searches' => [['searches' => [], 'queries' => []]],
        ];
    }

    /** @param  array<array-key, mixed>  $body */
    #[Test]
    #[DataProvider('shapelessBodies')]
    public function it_refuses_a_body_without_exactly_one_list_of_searches(array $body): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage('The search body must hold exactly one list of searches, under one of the fields [searches, queries, requests]');

        Access::guardSearch($body, $this->ada);
    }

    #[Test]
    public function it_refuses_searches_that_are_not_a_list(): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage('The [searches] field of the Typesense search body must be a list of searches.');

        Access::guardSearch(['searches' => ['first' => ['collection' => $this->index()]]], $this->ada);
    }

    #[Test]
    public function it_refuses_a_search_that_is_not_an_object(): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage('The search [1] of the [searches] field of the Typesense search body must be an object of search parameters.');

        Access::guardSearch(['searches' => [['collection' => $this->index()], 'posts']], $this->ada);
    }

    #[Test]
    public function it_refuses_a_search_that_does_not_name_its_index(): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage('The search [0] of the Typesense search body must name its index in [collection], as a non-empty string.');

        Access::guardSearch(['searches' => [['q' => 'Scout']]], $this->ada);
    }

    #[Test]
    public function it_refuses_an_index_that_no_discovered_model_is_searched_in(): void
    {
        $this->expectException(UnknownSearchIndexException::class);
        $this->expectExceptionMessage('The search names the [other] index, which is not the index of a ControlledSearchable model whose control is discovered.');

        Access::guardSearch(['searches' => [['collection' => 'other']]], $this->ada);
    }

    #[Test]
    public function it_refuses_the_index_of_a_model_whose_control_is_only_declared_by_its_attribute(): void
    {
        $this->expectException(UnknownSearchIndexException::class);

        Access::guardSearch(['searches' => [['collection' => 'pages']]], $this->ada);
    }

    #[Test]
    public function it_refuses_an_index_that_two_discovered_models_are_searched_in(): void
    {
        Access::discoverControlsIn([dirname(__DIR__, 3).'/Fixtures/SearchGuardMirror']);

        $this->expectException(AmbiguousSearchIndexException::class);
        $this->expectExceptionMessage('cannot tell which control filters a search of it');

        Access::guardSearch(['searches' => [['collection' => $this->index()]]], $this->ada);
    }

    #[Test]
    public function it_refuses_a_shared_index_only_when_a_search_names_it(): void
    {
        Access::discoverControlsIn([dirname(__DIR__, 3).'/Fixtures/SearchGuardMirror']);

        $this->expectException(UnknownSearchIndexException::class);

        Access::guardSearch(['searches' => [['collection' => 'other']]], $this->ada);
    }

    /** @return array<string, array{string}> */
    public static function unguardableDrivers(): array
    {
        return [
            'a trusted engine' => ['memory'],
            'the database engine' => ['database'],
            'the collection engine' => ['collection'],
            'the null engine' => ['null'],
        ];
    }

    #[Test]
    #[DataProvider('unguardableDrivers')]
    public function it_refuses_a_model_whose_engine_has_no_guard(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $this->expectException(UnguardableSearchEngineException::class);
        $this->expectExceptionMessage(sprintf('The [%s] model searches with the [', GuardedArticle::class));

        Access::guardSearch(['searches' => [['collection' => $this->index()]]], $this->ada);
    }

    #[Test]
    public function it_refuses_a_body_whose_shape_is_not_the_one_of_the_engine_of_its_index(): void
    {
        $this->expectException(InvalidSearchBodyException::class);
        $this->expectExceptionMessage(sprintf('The search body has the shape of a Meilisearch search, but the [%s] index is searched with Typesense.', $this->index()));

        Access::guardSearch(['queries' => [['indexUid' => $this->index()]]], $this->ada);
    }

    #[Test]
    public function it_refuses_a_control_whose_boundary_has_no_search_form(): void
    {
        ConfiguredGuardedArticleControl::bindWithin(new WorkspaceBoundary, AuthorPerimeter::new()->allowed(fn (User $user): bool => true)->unrestricted());

        $this->expectException(UnsearchableBoundaryException::class);

        Access::guardSearch(['searches' => [['collection' => $this->index()]]], $this->ada);
    }

    #[Test]
    public function it_gives_a_closed_reader_a_search_that_finds_nothing(): void
    {
        $this->assertSame('_access:=[`#closed`]', $this->guarded([], null));
        $this->assertSame('(featured:=true) && (_access:=[`#closed`])', $this->guarded(['filter_by' => 'featured:=true'], null));

        Gate::define('view own articles', fn (User $user): bool => false);

        $this->assertSame('_access:=[`#closed`]', $this->guarded([], $this->ada));
    }

    #[Test]
    public function it_gives_an_unrestricted_reader_the_filter_of_the_browser_alone(): void
    {
        $this->allow(['view global articles']);

        $this->assertNull($this->guarded([], $this->ada));
        $this->assertSame('featured:=true', $this->guarded(['filter_by' => 'featured:=true'], $this->ada));
    }

    #[Test]
    public function it_keeps_the_rows_of_a_reader_inside_the_boundary(): void
    {
        ConfiguredGuardedArticleControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
        );

        $this->assertSame('_boundary:=[`workspace_id.1`]', $this->guarded([], $this->ada));
    }

    #[Test]
    public function it_guards_every_search_of_the_body_with_the_filter_of_the_reader(): void
    {
        $access = $this->accessFilter(new TypesenseSyntax, $this->ada);

        $body = Access::guardSearch(['union' => true, 'searches' => [
            ['collection' => $this->index(), 'q' => 'Scout'],
            ['collection' => $this->index(), 'q' => 'Recipes', 'filter_by' => 'featured:=true'],
        ]], $this->ada);

        $this->assertSame(['union' => true, 'searches' => [
            ['collection' => $this->index(), 'q' => 'Scout', 'filter_by' => $access, 'filter_curated_hits' => true],
            ['collection' => $this->index(), 'q' => 'Recipes', 'filter_by' => "(featured:=true) && ({$access})", 'filter_curated_hits' => true],
        ]], $body);
    }

    #[Test]
    public function it_needs_no_declaration_of_the_search_tokens(): void
    {
        $this->assertNotNull($this->guarded([], $this->ada));

        Access::searchTokens(direct: false);

        $this->assertNotNull($this->guarded([], $this->ada));
    }

    #[Test]
    public function it_answers_a_refused_body_with_an_unprocessable_response(): void
    {
        Route::post('/search', fn (Request $request): array => Access::guardSearch($request->json()->all(), $request->user()));
        $this->actingAs($this->ada);

        $this->postJson('/search', ['searches' => [['collection' => $this->index(), 'pinned_hits' => '1:1']]])
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'sends [pinned_hits], which is not one of the parameters that a guarded search accepts'));
        $this->postJson('/search', ['searches' => [['collection' => $this->index()]]])
            ->assertOk()
            ->assertJsonPath('searches.0.filter_curated_hits', true);
    }

    #[Test]
    public function it_throws_http_exceptions_for_the_refusals_of_a_body(): void
    {
        $this->assertTrue(is_subclass_of(InvalidSearchBodyException::class, UnprocessableEntityHttpException::class));
        $this->assertTrue(is_subclass_of(UnknownSearchIndexException::class, UnprocessableEntityHttpException::class));
        $this->assertFalse(is_subclass_of(UnguardableSearchEngineException::class, UnprocessableEntityHttpException::class));
        $this->assertFalse(is_subclass_of(AmbiguousSearchIndexException::class, UnprocessableEntityHttpException::class));
    }

    #[Test]
    public function it_hides_the_soft_deleted_documents_when_scout_keeps_them(): void
    {
        config(['scout.soft_delete' => true]);
        $access = "_access:=[`author.{$this->ada->getKey()}`] && __soft_deleted:=0";

        $this->assertSame($access, $this->guarded([], $this->ada));
        $this->assertSame("(featured:=true) && ({$access})", $this->guarded(['filter_by' => 'featured:=true'], $this->ada));

        $this->allow(['view global articles']);
        Access::forgetDecisions();

        $this->assertSame('__soft_deleted:=0', $this->guarded([], $this->ada));
    }
}
