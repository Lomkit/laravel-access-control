<?php

namespace Lomkit\Access\Tests\Feature\Search;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\UndeclaredSearchKeyRelationException;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\SearchTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class LazyLoadingKeysTest extends SearchTestCase
{
    protected Project $website;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->website = Project::factory()->for(Client::factory()->createOne(['name' => 'Acme']))->createOne(['name' => 'Website']);
    }

    /** @param  list<string>  $load */
    protected function bindClientKeys(array $load): void
    {
        ConfiguredArticleControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys(
                    name: 'client',
                    row: fn (Article $article): mixed => $article->project?->client?->getKey(),
                    reader: fn (User $user): mixed => $user->client_id,
                    load: $load,
                ),
        );
    }

    #[Test]
    public function it_throws_when_a_row_callback_reads_a_relation_that_load_does_not_declare(): void
    {
        $this->bindClientKeys(['project']);
        $article = Article::withoutSyncingToSearch(fn (): Article => Article::factory()->for($this->website)->createOne(['title' => 'Scout primer']));

        $this->expectException(UndeclaredSearchKeyRelationException::class);
        $this->expectExceptionMessage(sprintf('The row callback of the [client] search keys reads the [client] relation of the [%s] model, which its load does not declare.', Project::class));

        $article->searchable();
    }

    #[Test]
    public function it_throws_for_a_row_that_was_just_created(): void
    {
        $this->bindClientKeys([]);

        $this->expectException(UndeclaredSearchKeyRelationException::class);

        Article::factory()->for($this->website)->createOne(['title' => 'Scout primer']);
    }

    #[Test]
    public function it_computes_the_keys_through_the_declared_relations(): void
    {
        $this->bindClientKeys(['project.client']);

        $article = Article::factory()->for($this->website)->createOne(['title' => 'Scout primer']);

        $this->assertSame(["client.{$this->website->client_id}"], $this->document($article)['_access']);
    }

    #[Test]
    public function it_restores_the_lazy_loading_setting_of_the_application(): void
    {
        $this->bindClientKeys([]);
        $article = Article::withoutSyncingToSearch(fn (): Article => Article::factory()->for($this->website)->createOne(['title' => 'Scout primer']));

        try {
            $article->searchable();
        } catch (UndeclaredSearchKeyRelationException) {
        }

        $this->assertFalse(Model::preventsLazyLoading());
        $this->assertInstanceOf(Project::class, $article->project);
    }

    #[Test]
    public function it_restores_a_lazy_loading_prevention_the_application_turned_on(): void
    {
        $this->bindClientKeys(['project.client']);
        Model::preventLazyLoading();

        try {
            Article::factory()->for($this->website)->createOne(['title' => 'Scout primer']);

            $this->assertTrue(Model::preventsLazyLoading());
        } finally {
            Model::preventLazyLoading(false);
        }
    }
}
