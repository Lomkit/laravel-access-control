<?php

namespace Lomkit\Access\Tests\Feature\Search;

use DateTimeImmutable;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\AlgoliaEngine;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Engines\MeilisearchEngine;
use Laravel\Scout\Engines\TurbopufferEngine;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Access\Access;
use Lomkit\Access\Boundaries\Boundary;
use Lomkit\Access\Exceptions\AdminSearchTokenKeyException;
use Lomkit\Access\Exceptions\InvalidSearchTokenKeyException;
use Lomkit\Access\Exceptions\MismatchedSearchTokenKeyException;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Exceptions\MissingSearchClientException;
use Lomkit\Access\Exceptions\PastSearchTokenExpiryException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Exceptions\UnsupportedSearchTokenEngineException;
use Lomkit\Access\Search\SearchKeyDigest;
use Lomkit\Access\Search\Tokens\SearchToken;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Tests\Fixtures\Blog\Post;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Blog\WorkspaceBoundary;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\AuthorPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Meilisearch\Client as MeilisearchClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Typesense\Client as TypesenseClient;

#[Group('scout')]
class SearchTokenTest extends SearchTestCase
{
    protected User $ada;

    protected User $grace;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user('Ada');
        $this->grace = $this->user('Grace');
        $this->freezeTime();
        $this->useEngine('algolia-stub', $this->createStub(AlgoliaEngine::class));
        Access::searchTokens(direct: true);
    }

    protected function useEngine(string $driver, Engine $engine): void
    {
        $this->app->make(EngineManager::class)->extend($driver, fn (): Engine => $engine);
        config(['scout.driver' => $driver]);
    }

    protected function token(?User $reader, ?SearchTokenKey $key = null): SearchToken
    {
        return Access::searchToken($reader, Article::class, $key ?? SearchTokenKey::algolia('search-only-key'), now()->addMinutes(15));
    }

    /** @return array<array-key, mixed> */
    protected function restrictionsOf(SearchToken $token): array
    {
        $content = base64_decode($token->token(), true);
        $this->assertIsString($content);
        $this->assertSame(hash_hmac('sha256', substr($content, 64), 'search-only-key'), substr($content, 0, 64));
        parse_str(substr($content, 64), $restrictions);

        return $restrictions;
    }

    #[Test]
    public function it_signs_the_keys_of_the_reader_into_a_token_restricted_to_the_index_of_the_model(): void
    {
        $this->allow(['view own articles', 'view featured articles']);

        $token = $this->token($this->ada);

        $filter = sprintf("(_access:'%s' OR _access:'%s')", ...SearchKeyDigest::all(["author.{$this->ada->getKey()}", 'featured.yes']));
        $this->assertSame($filter, $token->filter());
        $this->assertSame('articles', $token->index());
        $this->assertSame(['filters' => $filter, 'restrictIndices' => 'articles', 'validUntil' => (string) now()->addMinutes(15)->getTimestamp()], $this->restrictionsOf($token));
    }

    #[Test]
    public function it_signs_only_the_keys_of_the_level_the_reader_reaches(): void
    {
        $this->allow(['view own articles']);

        $this->assertSame(sprintf("(_access:'%s')", SearchKeyDigest::of("author.{$this->grace->getKey()}")), $this->token($this->grace)->filter());
    }

    #[Test]
    public function it_signs_the_boundary_and_the_access_keys_with_an_escaped_key(): void
    {
        ConfiguredArticleControl::bindWithin(
            Boundary::where('workspace_id', fn (User $user): ?int => $user->current_workspace_id),
            AuthorPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('author', fn (Article $article): mixed => $article->author_id, fn (User $user): array => [$user->getKey(), $user->name]),
        );

        $this->assertSame(
            sprintf("(_boundary:'%s') AND (_access:'%s' OR _access:'%s')", ...SearchKeyDigest::all(['workspace_id.1', "author.{$this->ada->getKey()}", 'author._416461'])),
            $this->token($this->ada)->filter(),
        );
    }

    #[Test]
    public function it_signs_no_filter_for_an_unrestricted_reader_without_a_boundary(): void
    {
        $this->allow(['view global articles']);

        $token = $this->token($this->ada);

        $this->assertNull($token->filter());
        $this->assertSame(['restrictIndices' => 'articles', 'validUntil' => (string) now()->addMinutes(15)->getTimestamp()], $this->restrictionsOf($token));
    }

    #[Test]
    public function it_signs_a_filter_that_finds_nothing_for_a_reader_no_perimeter_allows(): void
    {
        $token = $this->token($this->ada);

        $this->assertSame("(_access:'#closed')", $token->filter());
        $this->assertSame("(_access:'#closed')", $this->restrictionsOf($token)['filters']);
    }

    #[Test]
    public function it_signs_a_filter_that_finds_nothing_without_a_reader(): void
    {
        $this->allow(['view global articles']);

        $this->assertSame("(_access:'#closed')", $this->token(null)->filter());
    }

    #[Test]
    public function it_restricts_the_token_to_the_prefixed_index_of_the_model(): void
    {
        config(['scout.prefix' => 'tenant_']);
        $this->allow(['view global articles']);

        $token = $this->token($this->ada);

        $this->assertSame('tenant_articles', $token->index());
        $this->assertSame('tenant_articles', $this->restrictionsOf($token)['restrictIndices']);
    }

    #[Test]
    public function it_serializes_the_token_the_index_and_the_expiry_for_a_json_response(): void
    {
        $this->allow(['view own articles']);

        $token = $this->token($this->ada);

        $expected = ['token' => $token->token(), 'index' => 'articles', 'expires_at' => now()->addMinutes(15)->toAtomString()];
        $this->assertSame($expected, $token->toArray());
        $this->assertSame(json_encode($expected), json_encode($token));
        $this->assertEquals(now()->addMinutes(15)->toDateTimeImmutable(), $token->expiresAt());
    }

    #[Test]
    public function it_refuses_an_expiry_that_is_not_in_the_future(): void
    {
        $this->expectException(PastSearchTokenExpiryException::class);
        $this->expectExceptionMessage(sprintf('The search token for the [%s] model would expire at [%s], which is not in the future.', Article::class, now()->toAtomString()));

        Access::searchToken($this->ada, Article::class, SearchTokenKey::algolia('search-only-key'), now());
    }

    #[Test]
    public function it_refuses_an_expiry_in_the_past(): void
    {
        $this->expectException(PastSearchTokenExpiryException::class);

        Access::searchToken($this->ada, Article::class, SearchTokenKey::algolia('search-only-key'), new DateTimeImmutable('-1 minute'));
    }

    /** @return array<string, array{class-string<Engine>, string, SearchTokenKey}> */
    public static function adminKeys(): array
    {
        return [
            'Meilisearch' => [MeilisearchEngine::class, 'scout.meilisearch.key', SearchTokenKey::meilisearch(uid: 'key-uid', key: 'admin-key')],
            'Typesense' => [TypesenseEngine::class, 'scout.typesense.client-settings.api_key', SearchTokenKey::typesense('admin-key')],
            'Algolia' => [AlgoliaEngine::class, 'scout.algolia.secret', SearchTokenKey::algolia('admin-key')],
        ];
    }

    /** @param  class-string<Engine>  $engine */
    #[Test]
    #[DataProvider('adminKeys')]
    public function it_refuses_to_sign_with_the_admin_key_of_scout(string $engine, string $option, SearchTokenKey $key): void
    {
        $this->useEngine('stub', $this->createStub($engine));
        config([$option => 'admin-key']);

        $this->expectException(AdminSearchTokenKeyException::class);
        $this->expectExceptionMessage(sprintf('search token for the [%s] model is the admin key of config [%s]. A token signed with it would carry the rights of that key to the browser. Create a search-only', Article::class, $option));

        $this->token($this->ada, $key);
    }

    #[Test]
    public function it_refuses_a_key_of_another_engine(): void
    {
        $this->expectException(MismatchedSearchTokenKeyException::class);
        $this->expectExceptionMessage(sprintf('A Meilisearch key cannot sign a search token for the [%s] model, which searches with the Algolia engine. Pass SearchTokenKey::algolia(key: ...).', Article::class));

        $this->token($this->ada, SearchTokenKey::meilisearch(uid: 'key-uid', key: 'search-only-key'));
    }

    /** @return array<string, array{string}> */
    public static function enginesWithoutTokens(): array
    {
        return [
            'database' => ['database'],
            'collection' => ['collection'],
            'null' => ['null'],
            'trusted' => ['memory'],
        ];
    }

    #[Test]
    #[DataProvider('enginesWithoutTokens')]
    public function it_refuses_an_engine_without_browser_tokens(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $engine = (new Article)->searchableUsing();

        $this->expectException(UnsupportedSearchTokenEngineException::class);
        $this->expectExceptionMessage(sprintf('The [%s] model searches with the [%s] engine, which has no browser search token', Article::class, $engine::class));

        $this->token($this->ada);
    }

    #[Test]
    public function it_refuses_the_turbopuffer_engine(): void
    {
        if (! class_exists(TurbopufferEngine::class)) {
            $this->markTestSkipped('This Scout version has no Turbopuffer engine.');
        }

        config(['scout.driver' => 'turbopuffer']);

        $this->expectException(UnsupportedSearchTokenEngineException::class);
        $this->expectExceptionMessage(sprintf('searches with the [%s] engine, which has no browser search token', TurbopufferEngine::class));

        $this->token($this->ada);
    }

    #[Test]
    public function it_asks_for_the_meilisearch_client_when_it_is_missing(): void
    {
        if (class_exists(MeilisearchClient::class)) {
            $this->markTestSkipped('The Meilisearch client is installed.');
        }

        $this->useEngine('stub', $this->createStub(MeilisearchEngine::class));

        $this->expectException(MissingSearchClientException::class);
        $this->expectExceptionMessage('Signing a Meilisearch search token needs the official Meilisearch client, which is not installed or did not return a token. Run composer require meilisearch/meilisearch-php.');

        $this->token($this->ada, SearchTokenKey::meilisearch(uid: 'key-uid', key: 'search-only-key'));
    }

    #[Test]
    public function it_asks_for_the_typesense_client_when_it_is_missing(): void
    {
        if (class_exists(TypesenseClient::class)) {
            $this->markTestSkipped('The Typesense client is installed.');
        }

        $this->useEngine('stub', $this->createStub(TypesenseEngine::class));

        $this->expectException(MissingSearchClientException::class);
        $this->expectExceptionMessage('Run composer require typesense/typesense-php.');

        $this->token($this->ada, SearchTokenKey::typesense('search-only-key'));
    }

    #[Test]
    public function it_refuses_a_model_that_does_not_use_controlled_searchable(): void
    {
        $this->expectException(MissingControlledSearchableException::class);
        $this->expectExceptionMessage(sprintf('No search token can be signed for [%s]: it is not an Eloquent model that uses the ControlledSearchable trait', Post::class));

        Access::searchToken($this->ada, Post::class, SearchTokenKey::algolia('search-only-key'), now()->addMinutes(15));
    }

    #[Test]
    public function it_refuses_a_control_whose_boundary_has_no_search_form(): void
    {
        ConfiguredArticleControl::bindWithin(new WorkspaceBoundary, AuthorPerimeter::new()->allowed(fn (User $user): bool => true)->unrestricted());

        $this->expectException(UnsearchableBoundaryException::class);

        $this->token($this->ada);
    }

    /** @return array<string, array{callable(): SearchTokenKey, string}> */
    public static function blankKeys(): array
    {
        return [
            'a blank Meilisearch uid' => [fn (): SearchTokenKey => SearchTokenKey::meilisearch(uid: ' ', key: 'search-only-key'), 'The Meilisearch search token key has a blank uid.'],
            'a blank Meilisearch key' => [fn (): SearchTokenKey => SearchTokenKey::meilisearch(uid: 'key-uid', key: ''), 'The Meilisearch search token key has a blank key.'],
            'a blank Typesense key' => [fn (): SearchTokenKey => SearchTokenKey::typesense(' '), 'The Typesense search token key has a blank key.'],
            'a blank Algolia key' => [fn (): SearchTokenKey => SearchTokenKey::algolia(''), 'The Algolia search token key has a blank key.'],
        ];
    }

    /** @param  callable(): SearchTokenKey  $key */
    #[Test]
    #[DataProvider('blankKeys')]
    public function it_refuses_a_blank_key(callable $key, string $message): void
    {
        $this->expectException(InvalidSearchTokenKeyException::class);
        $this->expectExceptionMessage($message);

        $key();
    }

    #[Test]
    public function it_keeps_the_key_out_of_a_dump(): void
    {
        $dump = print_r(SearchTokenKey::meilisearch(uid: 'key-uid', key: 'search-only-key'), true);

        $this->assertStringContainsString('key-uid', $dump);
        $this->assertStringNotContainsString('search-only-key', $dump);
    }
}
