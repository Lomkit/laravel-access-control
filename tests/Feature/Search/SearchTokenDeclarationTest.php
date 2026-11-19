<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\AlgoliaEngine;
use Laravel\Scout\Engines\Engine;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\ConflictingSearchTokensException;
use Lomkit\Access\Exceptions\ProxiedSearchTokenException;
use Lomkit\Access\Exceptions\UndeclaredSearchTokensException;
use Lomkit\Access\Search\Tokens\SearchToken;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SearchTokenDeclarationTest extends SearchTestCase
{
    protected User $ada;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user('Ada');
        $this->allow(['view own articles']);
        $engine = $this->createStub(AlgoliaEngine::class);
        $this->app->make(EngineManager::class)->extend('algolia-stub', fn (): Engine => $engine);
        config(['scout.driver' => 'algolia-stub']);
    }

    protected function token(): SearchToken
    {
        return Access::searchToken($this->ada, Article::class, SearchTokenKey::algolia('search-only-key'), now()->addMinutes(15));
    }

    #[Test]
    public function it_refuses_to_sign_a_token_before_the_application_declares_how_browsers_reach_the_engine(): void
    {
        $this->expectException(UndeclaredSearchTokensException::class);
        $this->expectExceptionMessage(sprintf('No search token is signed for the [%s] model until the application declares how browsers reach the search engine.', Article::class));
        $this->expectExceptionMessage('Access::searchTokens(direct: true)');
        $this->expectExceptionMessage('Access::searchTokens(direct: false)');
        $this->expectExceptionMessage('UPGRADE.md#search-from-the-browser');

        $this->token();
    }

    #[Test]
    public function it_refuses_an_undeclared_token_before_it_checks_the_expiry(): void
    {
        $this->expectException(UndeclaredSearchTokensException::class);

        Access::searchToken($this->ada, Article::class, SearchTokenKey::algolia('search-only-key'), now()->subMinute());
    }

    #[Test]
    public function it_signs_a_token_when_browsers_query_the_engine_directly(): void
    {
        Access::searchTokens(direct: true);

        $this->assertSame('articles', $this->token()->index());
    }

    #[Test]
    public function it_refuses_to_sign_a_token_when_browsers_go_through_the_route_of_the_application(): void
    {
        Access::searchTokens(direct: false);

        $this->expectException(ProxiedSearchTokenException::class);
        $this->expectExceptionMessage(sprintf('No search token is signed for the [%s] model: the application declared Access::searchTokens(direct: false)', Article::class));
        $this->expectExceptionMessage('Access::guardSearch()');

        $this->token();
    }

    #[Test]
    public function it_accepts_the_same_declaration_twice(): void
    {
        Access::searchTokens(direct: true);
        Access::searchTokens(direct: true);

        $this->assertSame('articles', $this->token()->index());
    }

    #[Test]
    public function it_refuses_a_second_declaration_that_differs_from_the_first(): void
    {
        Access::searchTokens(direct: false);

        $this->expectException(ConflictingSearchTokensException::class);
        $this->expectExceptionMessage('The application declared Access::searchTokens(direct: false), then Access::searchTokens(direct: true). An application makes one choice');

        Access::searchTokens(direct: true);
    }

    #[Test]
    public function it_keeps_the_first_declaration_after_a_conflicting_one(): void
    {
        Access::searchTokens(direct: true);

        try {
            Access::searchTokens(direct: false);
            $this->fail('The second declaration was accepted.');
        } catch (ConflictingSearchTokensException) {
        }

        $this->assertSame('articles', $this->token()->index());
    }
}
