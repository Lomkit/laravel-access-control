<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Laravel\Scout\EngineManager;
use Lomkit\Access\Exceptions\NaturalLanguageSearchException;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use Lomkit\Access\Tests\Fixtures\Search\TypesenseRequestEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class TypesenseNaturalLanguageTest extends SearchTestCase
{
    protected User $ada;

    protected TypesenseRequestEngine $typesense;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $typesense = $this->typesense = new TypesenseRequestEngine;
        $this->app->make(EngineManager::class)->extend('typesense-requests', fn (): TypesenseRequestEngine => $typesense);
        config(['scout.driver' => 'typesense-requests']);
        $this->ada = $this->user('Ada');
    }

    /** @return array<string, array{mixed}> */
    public static function naturalLanguage(): array
    {
        return [
            'true' => [true],
            'the string true' => ['true'],
            'another value' => [1],
        ];
    }

    #[Test]
    #[DataProvider('naturalLanguage')]
    public function it_refuses_natural_language_in_the_options_before_or_after_controlled(mixed $value): void
    {
        $this->allow(['view own articles']);

        foreach ([true, false] as $before) {
            try {
                $search = Article::search('posts by grace');
                $search = $before ? $search->options(['nl_query' => $value])->controlledFor($this->ada) : $search->controlledFor($this->ada)->options(['nl_query' => $value]);
                $search->get();
                $this->fail('A controlled search ran with natural-language search turned on.');
            } catch (NaturalLanguageSearchException $exception) {
                $this->assertStringContainsString(sprintf('The nl_query that options() gives to a controlled Typesense search of the [%s] model', Article::class), $exception->getMessage());
            }
        }

        $this->assertSame([], $this->typesense->requests);
    }

    #[Test]
    public function it_refuses_natural_language_that_the_model_turns_on(): void
    {
        $this->allow(['view own articles']);
        config(['testing.article.typesense-search-parameters' => ['nl_query' => true]]);

        $this->expectException(NaturalLanguageSearchException::class);
        $this->expectExceptionMessage('The nl_query that typesenseSearchParameters() gives');

        Article::search('posts by grace')->controlledFor($this->ada)->get();
    }

    #[Test]
    public function it_accepts_natural_language_turned_off(): void
    {
        $this->allow(['view own articles']);

        Article::search('Scout')->controlledFor($this->ada)->options(['nl_query' => false])->get();
        Article::search('Scout')->controlledFor($this->ada)->options(['nl_query' => 'false'])->get();

        $this->assertCount(2, $this->typesense->requests);
    }

    #[Test]
    public function it_leaves_natural_language_to_an_unrestricted_reader_without_an_access_filter(): void
    {
        $this->allow(['view global articles']);

        Article::search('posts by grace')->controlledFor($this->ada)->options(['nl_query' => true])->get();

        $this->assertTrue($this->typesense->requests[0]['nl_query']);
    }
}
