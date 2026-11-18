<?php

namespace Lomkit\Access\Tests\Fixtures\Search;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Lomkit\Access\Access;
use Lomkit\Access\Search\Tokens\SearchToken;
use PHPUnit\Framework\Attributes\Test;
use Typesense\Client;

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

    protected function internalsId(): string
    {
        return (string) Article::query()->where('title', 'Scout internals')->value('id');
    }

    protected function typesenseAdmin(): PendingRequest
    {
        $settings = config('scout.typesense.client-settings');
        $node = $settings['nodes'][0];

        return Http::baseUrl("{$node['protocol']}://{$node['host']}:{$node['port']}")
            ->withHeaders(['X-TYPESENSE-API-KEY' => $settings['api_key']]);
    }

    protected function presetThatShowsCuratedHits(): string
    {
        $preset = (new Article)->searchableAs().'_curated';
        $this->typesenseAdmin()->put("/presets/{$preset}", ['value' => ['filter_curated_hits' => false, 'pinned_hits' => "{$this->internalsId()}:1"]])->throw();
        $this->beforeApplicationDestroyed(fn () => $this->typesenseAdmin()->delete("/presets/{$preset}"));

        return $preset;
    }

    protected function curateInternalsFirst(): void
    {
        $index = (new Article)->searchableAs();
        $set = "{$index}_curations";
        $http = $this->typesenseAdmin();

        $http->put("/curation_sets/{$set}", ['items' => [[
            'id' => 'internals-first',
            'rule' => ['query' => 'Scout', 'match' => 'contains'],
            'includes' => [['id' => $this->internalsId(), 'position' => 1]],
        ]]])->throw();
        $http->patch("/collections/{$index}", ['curation_sets' => [$set]])->throw();
        $this->beforeApplicationDestroyed(fn () => $http->delete("/curation_sets/{$set}"));
    }

    /** @return list<string> */
    protected function titlesFoundWithPreset(SearchToken $token, string $preset): array
    {
        $results = (new Client($this->clientSettings($token->token())))
            ->collections[$token->index()]
            ->documents
            ->search(['q' => 'Scout', 'query_by' => 'title', 'per_page' => 100, 'preset' => $preset]);

        return array_values(array_map(fn (array $hit): string => $hit['document']['title'], $results['hits']));
    }

    /** @return list<string> */
    protected function titlesFoundWithPinnedHits(SearchToken $token, string $pinnedHits): array
    {
        $results = (new Client($this->clientSettings($token->token())))
            ->collections[$token->index()]
            ->documents
            ->search(['q' => 'Scout', 'query_by' => 'title', 'per_page' => 100, 'pinned_hits' => $pinnedHits, 'filter_curated_hits' => false]);
        $titles = array_map(fn (array $hit): string => $hit['document']['title'], $results['hits']);
        sort($titles);

        return $titles;
    }

    #[Test]
    public function it_leaves_out_a_pinned_hit_outside_the_access_of_the_reader(): void
    {
        $this->allow(['view own articles']);

        $this->assertSame(['Scout primer'], $this->titlesWithOptions(['pinned_hits' => "{$this->internalsId()}:1"], beforeControlled: true));
        $this->assertSame(['Scout primer'], $this->titlesWithOptions(['pinned_hits' => "{$this->internalsId()}:1", 'filter_curated_hits' => false], beforeControlled: false));
        $this->assertSame(['Scout primer'], $this->titlesWithOptions(['preset' => $this->presetThatShowsCuratedHits()], beforeControlled: false));
    }

    #[Test]
    public function it_leaves_out_a_curated_hit_outside_the_access_of_the_reader(): void
    {
        $this->allow(['view own articles']);
        $this->curateInternalsFirst();

        $first = Article::search('Scout')
            ->uncontrolled()
            ->get()
            ->first();

        $this->assertSame('Scout internals', $first?->getAttribute('title'));
        $this->assertSame(['Scout primer'], $this->searchedTitles());
    }

    #[Test]
    public function it_signs_a_token_that_leaves_out_the_curated_and_pinned_hits_outside_the_access_of_the_reader(): void
    {
        $this->allow(['view own articles']);
        $this->curateInternalsFirst();
        $token = Access::searchToken($this->ada, Article::class, $this->searchOnlyKey((new Article)->searchableAs()), now()->addMinutes(10));

        $this->assertSame(['Scout primer'], $this->titlesFoundWith($token, $token->index()));
        $this->assertSame(['Scout primer'], $this->titlesFoundWithPinnedHits($token, "{$this->internalsId()}:1"));
        $this->assertSame(['Scout primer'], $this->titlesFoundWithPreset($token, $this->presetThatShowsCuratedHits()));
    }
}
