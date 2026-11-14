<?php

namespace Lomkit\Access\Tests\Feature\Search\Freshness;

use Closure;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Search\Freshness\Inspection\AccessKeysNotWritten;
use Lomkit\Access\Search\Freshness\Inspection\FreshnessInspector;
use Lomkit\Access\Search\Freshness\Inspection\FreshnessIssue;
use Lomkit\Access\Search\Freshness\Inspection\IndexCheckOverridden;
use Lomkit\Access\Search\Freshness\Inspection\ScoutIndexesBeforeCommit;
use Lomkit\Access\Search\Freshness\Inspection\UnwatchedKeyRelation;
use Lomkit\Access\Tests\Fixtures\Blog\Client;
use Lomkit\Access\Tests\Fixtures\Blog\Project;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\ConfiguredArticleControl;
use Lomkit\Access\Tests\Fixtures\Search\Article;
use Lomkit\Access\Tests\Fixtures\Search\ClientPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\Folder;
use Lomkit\Access\Tests\Fixtures\Search\FolderClientBoundary;
use Lomkit\Access\Tests\Fixtures\Search\FreshnessTestCase;
use Lomkit\Access\Tests\Fixtures\Search\KeylessArticle;
use Lomkit\Access\Tests\Fixtures\Search\Notice;
use Lomkit\Access\Tests\Fixtures\Search\QuietArticle;
use Lomkit\Access\Tests\Fixtures\Search\SharedPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\Snippet;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[WithConfig('scout.after_commit', true)]
class FreshnessInspectorTest extends FreshnessTestCase
{
    /** @param  list<string>  $load */
    protected function bindClientKeys(array $load, ?Closure $declare = null): void
    {
        $perimeter = ClientPerimeter::new()
            ->allowed(fn (User $user): bool => true)
            ->searchKeys('client', fn (Article $article): mixed => $article->project?->client_id, fn (User $user): mixed => $user->client_id, $load);

        ConfiguredArticleControl::bind($declare === null ? $perimeter : $declare($perimeter));
    }

    /**
     * @param  list<FreshnessIssue>  $issues
     * @return list<array{string, string, ?string}>
     */
    protected function relations(array $issues): array
    {
        return array_values(array_map(
            fn (UnwatchedKeyRelation $issue): array => [$issue->keys(), $issue->relation(), $issue->related()],
            array_filter($issues, fn (FreshnessIssue $issue): bool => $issue instanceof UnwatchedKeyRelation),
        ));
    }

    #[Test]
    public function it_reports_a_model_whose_keys_stay_fresh(): void
    {
        $report = FreshnessInspector::inspect(Article::class);

        $this->assertTrue($report->isFresh());
        $this->assertSame([], $report->issues());
        $this->assertSame(Article::class, $report->model());
    }

    #[Test]
    #[WithConfig('scout.after_commit', false)]
    public function it_reports_that_scout_indexes_before_the_commit(): void
    {
        $issues = FreshnessInspector::inspect(Article::class)->issues();

        $this->assertEquals([new ScoutIndexesBeforeCommit(Article::class)], $issues);
        $this->assertSame(Article::class, $issues[0]->model());
        $this->assertStringContainsString('Scout\'s after_commit option is off', $issues[0]->message());
    }

    #[Test]
    public function it_reports_a_model_that_overrides_the_index_check(): void
    {
        $issues = FreshnessInspector::inspect(QuietArticle::class)->issues();

        $this->assertEquals([new IndexCheckOverridden(QuietArticle::class)], $issues);
        $this->assertStringContainsString(sprintf('The [%s] model overrides searchIndexShouldBeUpdated().', QuietArticle::class), $issues[0]->message());
    }

    #[Test]
    public function it_reports_a_model_that_overrides_makesearchableusing_without_writing_the_keys(): void
    {
        $issues = FreshnessInspector::inspect(KeylessArticle::class)->issues();

        $this->assertEquals([new AccessKeysNotWritten(KeylessArticle::class)], $issues);
        $this->assertStringContainsString('its body never calls withAccessKeys()', $issues[0]->message());
    }

    #[Test]
    public function it_trusts_an_override_of_makesearchableusing_that_calls_withaccesskeys(): void
    {
        $this->assertTrue(FreshnessInspector::inspect(Notice::class)->isFresh());
    }

    #[Test]
    public function it_reports_a_key_that_reads_a_relation_nothing_watches(): void
    {
        $this->bindClientKeys(['project:id,client_id']);

        $issues = FreshnessInspector::inspect(Article::class)->issues();

        $this->assertSame([['client', 'project', Project::class]], $this->relations($issues));
        $this->assertStringContainsString('Declare reindexWhen(Project::class, ...) on the perimeter', $issues[0]->message());
    }

    #[Test]
    public function it_trusts_a_relation_whose_model_is_watched(): void
    {
        $this->bindClientKeys(['project:id,client_id'], fn (ClientPerimeter $perimeter): ClientPerimeter => $perimeter->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => Article::query()->where('project_id', $project->getKey())));

        $this->assertTrue(FreshnessInspector::inspect(Article::class)->isFresh());
    }

    #[Test]
    public function it_reports_each_unwatched_step_of_a_nested_relation(): void
    {
        $this->bindClientKeys(['project.client:id,name'], fn (ClientPerimeter $perimeter): ClientPerimeter => $perimeter->reindexWhen(Project::class, changed: ['client_id'], rows: fn (Project $project) => Article::query()->where('project_id', $project->getKey())));

        $this->assertSame([['client', 'project.client', Client::class]], $this->relations(FreshnessInspector::inspect(Article::class)->issues()));
    }

    #[Test]
    public function it_trusts_a_pivot_that_reindexes_the_search_and_reports_a_plain_one(): void
    {
        ConfiguredArticleControl::bind(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('shared', fn (Article $article): array => [...$article->reviewers->modelKeys(), ...$article->sharedWith->modelKeys()], fn (User $user): mixed => $user->getKey(), ['reviewers:id', 'sharedWith:id']),
        );

        $this->assertSame([['shared', 'sharedWith', User::class]], $this->relations(FreshnessInspector::inspect(Article::class)->issues()));
    }

    #[Test]
    public function it_reports_a_relation_that_the_model_does_not_declare(): void
    {
        $this->bindClientKeys(['ghost']);

        $this->assertSame([['client', 'ghost', null]], $this->relations(FreshnessInspector::inspect(Article::class)->issues()));
        $this->assertStringContainsString('reindexWhen(Related::class, ...)', FreshnessInspector::inspect(Article::class)->issues()[0]->message());
    }

    #[Test]
    public function it_reports_the_relations_that_the_boundary_keys_read(): void
    {
        ConfiguredArticleControl::bindWithin(
            new FolderClientBoundary,
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('client', fn (Article $article): mixed => $article->getAttribute('folder_id'), fn (User $user): mixed => $user->client_id),
        );

        $this->assertSame([['folder_client', 'folder', Folder::class]], $this->relations(FreshnessInspector::inspect(Article::class)->issues()));
    }

    #[Test]
    public function it_refuses_a_model_without_controlledsearchable(): void
    {
        $this->expectException(MissingControlledSearchableException::class);

        FreshnessInspector::inspect(Snippet::class);
    }

    #[Test]
    public function it_requires_the_related_model_to_be_watched_when_a_pivot_relation_reads_more_than_its_keys(): void
    {
        ConfiguredArticleControl::bind(
            SharedPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('reviewer', fn (Article $article): array => $article->reviewers->pluck('client_id')->all(), fn (User $user): mixed => $user->client_id, ['reviewers', 'reviewers:id,client_id']),
        );

        $this->assertSame([['reviewer', 'reviewers', User::class], ['reviewer', 'reviewers', User::class]], $this->relations(FreshnessInspector::inspect(Article::class)->issues()));
    }

    #[Test]
    public function it_reports_a_watched_relation_whose_read_attributes_the_rule_does_not_list(): void
    {
        $this->bindClientKeys(['project:id,client_id'], fn (ClientPerimeter $perimeter): ClientPerimeter => $perimeter->reindexWhen(Project::class, changed: ['name'], rows: fn (Project $project) => Article::query()->where('project_id', $project->getKey())));

        $this->assertSame([['client', 'project', Project::class]], $this->relations(FreshnessInspector::inspect(Article::class)->issues()));
    }

    #[Test]
    public function it_trusts_a_relation_to_a_subclass_of_a_watched_model(): void
    {
        ConfiguredArticleControl::bind(
            ClientPerimeter::new()
                ->allowed(fn (User $user): bool => true)
                ->searchKeys('client', fn (Article $article): mixed => $article->archiveFolder?->client_id, fn (User $user): mixed => $user->client_id, ['archiveFolder:id,client_id'])
                ->reindexWhen(Folder::class, changed: ['client_id'], rows: fn (Folder $folder) => $folder->articles()),
        );

        $this->assertTrue(FreshnessInspector::inspect(Article::class)->isFresh());
    }
}
