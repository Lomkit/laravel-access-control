<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\TypesenseEngine;
use Lomkit\Access\Search\SearchEngineKind;
use Lomkit\Access\Search\SearchKeyDigest;
use Lomkit\Access\Search\Tokens\Signers\SearchTokenSigners;
use Lomkit\Access\Search\Tokens\Signers\TypesenseSigner;
use Lomkit\Access\Search\TrustedSearchEngines;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Engines\MultiSearchTypesenseEngine;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class MultiSearchTypesenseEngineTest extends SearchTestCase
{
    protected User $ada;

    protected MultiSearchTypesenseEngine $multiSearch;

    #[\Override]
    protected function setUp(): void
    {
        if (! method_exists(TypesenseEngine::class, 'executeSearch')) {
            $this->markTestSkipped('This Scout version has no TypesenseEngine::executeSearch().');
        }

        parent::setUp();

        $this->ada = $this->user('Ada');
        $engine = $this->multiSearch = $this->createStub(MultiSearchTypesenseEngine::class);
        $this->app->make(EngineManager::class)->extend('typesense-multi-search', fn (): MultiSearchTypesenseEngine => $engine);
        config(['scout.driver' => 'typesense-multi-search']);
    }

    #[Test]
    public function it_trusts_a_subclass_of_the_typesense_engine_of_scout_without_declaring_it(): void
    {
        $this->assertTrue($this->app->make(TrustedSearchEngines::class)->trusts($this->multiSearch));
        $this->assertSame(SearchEngineKind::Index, SearchEngineKind::of($this->multiSearch, new Article));
        $this->assertInstanceOf(TypesenseSigner::class, SearchTokenSigners::for($this->multiSearch, new Article));
    }

    #[Test]
    public function it_filters_a_controlled_search_through_the_subclass_with_the_digests_of_the_keys(): void
    {
        $this->allow(['view own articles', 'view featured articles']);
        $search = Article::search('Scout')->controlledFor($this->ada);

        $search->raw();

        $this->assertSame(['_access' => SearchKeyDigest::all(["author.{$this->ada->getKey()}", 'featured.yes'])], $search->whereIns);
    }
}
