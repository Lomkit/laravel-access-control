<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use PHPUnit\Framework\Attributes\Test;

trait TypesenseAccessFilterEngineTests
{
    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    protected function titlesWithOptions(array $options, bool $beforeControlled): array
    {
        $search = Article::search('Scout');
        $search = $beforeControlled
            ? $search->options($options)->controlled()
            : $search->controlled()->options($options);

        return $search->get()
            ->pluck('title')
            ->sort()
            ->values()
            ->all();
    }

    #[Test]
    public function it_finds_only_the_rows_of_the_reader_when_the_app_passes_a_filter_by_before_controlled(): void
    {
        $this->allow(['view own articles']);

        $this->assertSame(['Scout primer'], $this->titlesWithOptions(['filter_by' => 'featured:=true || featured:=false'], beforeControlled: true));
    }

    #[Test]
    public function it_finds_only_the_rows_of_the_reader_when_the_app_passes_a_filter_by_after_controlled(): void
    {
        $this->allow(['view own articles']);

        $this->assertSame(['Scout primer'], $this->titlesWithOptions(['filter_by' => 'featured:=true || featured:=false'], beforeControlled: false));
        $page = Article::search('Scout')
            ->controlled()
            ->options(['filter_by' => 'featured:=true || featured:=false'])
            ->paginate(10);

        $this->assertSame(1, $page->total());
    }

    #[Test]
    public function it_finds_only_the_rows_of_the_reader_when_the_model_returns_a_filter_by(): void
    {
        $this->allow(['view own articles']);
        config(['testing.article.typesense-search-parameters' => ['filter_by' => 'featured:=true || featured:=false']]);

        $this->assertSame(['Scout primer'], $this->searchedTitles());
    }

    #[Test]
    public function it_narrows_the_rows_of_the_reader_with_the_filter_by_of_the_app(): void
    {
        $this->allow(['view own articles', 'view featured articles']);

        $this->assertSame(['Scout recipes'], $this->titlesWithOptions(['filter_by' => 'featured:=true'], beforeControlled: false));
        $this->assertSame(['Scout primer'], $this->titlesWithOptions(['filter_by' => 'title:=`Scout primer`'], beforeControlled: true));
    }

    #[Test]
    public function it_finds_only_the_rows_of_the_reader_through_a_search_callback_with_a_filter_by(): void
    {
        $this->allow(['view own articles']);

        $titles = Article::search('Scout', fn (mixed $documents, string $query, array $options): mixed => $documents->search($options))
            ->controlled()
            ->options(['filter_by' => 'featured:=true || featured:=false'])
            ->get()
            ->pluck('title')
            ->all();

        $this->assertSame(['Scout primer'], $titles);
    }
}
