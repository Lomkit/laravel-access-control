<?php

namespace Lomkit\Access\Tests\Feature\Perimeters;

use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\FeaturedPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\GlobalPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class UnrestrictedSearchTest extends SearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $ada = $this->user('Ada');
        $grace = $this->user('Grace');
        Article::factory()->createOne(['author_id' => $ada->getKey(), 'title' => 'Scout primer']);
        Article::factory()->createOne(['author_id' => $grace->getKey(), 'title' => 'Scout recipes', 'featured' => true]);
        Article::factory()->createOne(['author_id' => $grace->getKey(), 'title' => 'Scout internals']);
        $this->actingAs($ada);
    }

    #[Test]
    public function it_searches_every_article_through_an_unrestricted_perimeter(): void
    {
        $this->allow(['view global articles']);

        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_drops_the_key_filter_for_a_reader_with_the_global_and_the_featured_permissions(): void
    {
        $this->allow(['view global articles', 'view featured articles']);

        $search = Article::search('Scout')->controlled();

        $this->assertSame([], $search->whereIns);
        $this->assertNull($search->callback);
        $this->assertSame(['Scout internals', 'Scout primer', 'Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_searches_the_featured_articles_when_the_unrestricted_perimeter_does_not_allow_the_reader(): void
    {
        $this->allow(['view featured articles', 'update global articles']);

        $this->assertSame(['Scout recipes'], $this->searchedTitles());
    }

    #[Test]
    public function it_does_not_compute_the_reader_keys_of_the_other_applicable_perimeters(): void
    {
        $computed = [];
        ConfiguredArticleControl::bind(
            FeaturedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('featured', fn (Article $article): ?string => $article->featured ? 'yes' : null, function (User $user) use (&$computed): string {
                    $computed[] = 'featured';

                    return 'yes';
                }),
            GlobalPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->unrestricted(),
        );
        $this->reindex();

        $this->assertCount(3, $this->searchedTitles());
        $this->assertSame([], $computed);
    }
}
