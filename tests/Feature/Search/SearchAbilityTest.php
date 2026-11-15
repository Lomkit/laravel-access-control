<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\AlgoliaEngine;
use Lomkit\Access\Ability;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\IgnoredArgumentException;
use Lomkit\Access\Exceptions\UnsearchableAbilityException;
use Lomkit\Access\Search\Tokens\SearchTokenKey;
use Lomkit\Access\Tests\Fixtures\Blog\PostControl;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[WithConfig('auth.providers.users.model', User::class)]
class SearchAbilityTest extends SearchTestCase
{
    protected User $ada;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->ada = $this->user('Ada');
        Article::factory()->createOne(['author_id' => $this->ada->getKey(), 'title' => 'Scout primer']);
        $this->allow(['view own articles', 'update own articles']);
    }

    #[Test]
    public function it_refuses_an_ability_given_to_a_controlled_search_for_a_reader(): void
    {
        $this->expectException(UnsearchableAbilityException::class);
        $this->expectExceptionMessage('reachableKeys() or Access::preload()');

        Article::search('Scout')->controlledFor($this->ada, 'update');
    }

    #[Test]
    public function it_refuses_an_ability_given_to_a_controlled_search_for_the_signed_in_user(): void
    {
        $this->actingAs($this->ada);

        $this->expectException(UnsearchableAbilityException::class);

        Article::search('Scout')->controlled(Ability::update());
    }

    #[Test]
    public function it_still_searches_the_view_rows_without_an_ability(): void
    {
        $this->assertSame(['Scout primer'], Article::search('Scout')->controlledFor($this->ada)->get()->pluck('title')->all());
    }

    #[Test]
    public function it_refuses_an_ability_given_to_a_search_token(): void
    {
        $this->app->make(EngineManager::class)->extend('algolia-stub', fn (): AlgoliaEngine => $this->createStub(AlgoliaEngine::class));
        config(['scout.driver' => 'algolia-stub']);

        $this->expectException(UnsearchableAbilityException::class);

        Access::searchToken($this->ada, Article::class, SearchTokenKey::algolia('search-only-key'), now()->addMinutes(15), 'update');
    }

    #[Test]
    public function it_refuses_an_ability_given_to_assert_search_agrees(): void
    {
        $this->expectException(UnsearchableAbilityException::class);

        $this->assertSearchAgrees(ArticleControl::class, [$this->ada], 'update');
    }

    #[Test]
    public function it_refuses_an_ability_given_to_assert_levels_nest(): void
    {
        $this->expectException(IgnoredArgumentException::class);

        $this->assertLevelsNest(PostControl::class, [$this->ada], 'update');
    }
}
