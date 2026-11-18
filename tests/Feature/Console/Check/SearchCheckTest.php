<?php

namespace Lomkit\Access\Tests\Feature\Console\Check;

use Illuminate\Support\Facades\Artisan;
use Lomkit\Access\Access;
use Lomkit\Access\Exceptions\MissingControlledSearchableException;
use Lomkit\Access\Exceptions\UnsearchableBoundaryException;
use Lomkit\Access\Exceptions\UnsupportedSearchEngineException;
use Lomkit\Access\Search\TrustedSearchEngines;
use Lomkit\Access\Tests\Fixtures\Check\Perimeters\OwnPerimeter;
use Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Models\Document;
use Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Models\DocumentTag;
use Lomkit\Access\Tests\Fixtures\Check\Search\Clean\Models\TaggedDocument;
use Lomkit\Access\Tests\Fixtures\Check\Search\Pivot\Models\LooseTag;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Controls\BoundedDocumentControl;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Controls\KeylessDocumentControl;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Controls\RepeatedDocumentControl;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Controls\WrappedDocumentControl;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\BoundedDocument;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\Folder;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\KeylessDocument;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\PlainDocument;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\RelatedDocument;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\RepeatedDocument;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\RewrittenDocument;
use Lomkit\Access\Tests\Fixtures\Check\Search\Problems\Models\WrappedDocument;
use Lomkit\Access\Tests\Fixtures\Purity\WrappingPerimeter;
use Lomkit\Access\Tests\Fixtures\Search\MemoryEngine;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SearchCheckTest extends SearchCheckTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->discover(['Search/Clean', 'Search/Problems']);
        Access::registerPolicies();
        Access::trustSearchEngine(MemoryEngine::class);
    }

    #[Test]
    public function it_finds_no_problem_in_searchable_models_whose_documents_stay_fresh(): void
    {
        [$code, $report] = $this->checkJson(['--only' => [Document::class, TaggedDocument::class]]);

        $this->assertSame(0, $code);
        $this->assertSame([], $report['problems']);
        $this->assertSame(['searchable' => true, 'engine' => MemoryEngine::class], $report['controls'][0]['search']);
        $this->assertSame('memory', $report['discovery']['scout_driver']);
        $this->assertSame(1, $report['pivots']['relations']);
    }

    #[Test]
    public function it_prints_that_the_scout_driver_comes_from_the_environment(): void
    {
        $this->artisan('access:check', ['--only' => [Document::class]])
            ->expectsOutputToContain('memory, from the config of this environment')
            ->expectsOutputToContain('ControlledSearchable on '.MemoryEngine::class)
            ->assertExitCode(0);
    }

    #[Test]
    #[WithConfig('scout.after_commit', false)]
    public function it_reports_scout_indexing_before_the_commit(): void
    {
        $this->assertSame([
            ['kind' => 'search_freshness', 'subject' => Document::class, 'position' => null, 'message' => 'Scout\'s after_commit option is off, so Scout indexes a ['.Document::class.'] saved inside a transaction before it commits: a rolled back change can leave keys in the index that the database never kept. Set \'after_commit\' => true in config/scout.php.'],
        ], $this->problemsOf(Document::class));
    }

    #[Test]
    public function it_reports_an_engine_that_the_package_does_not_trust(): void
    {
        $this->app->forgetInstance(TrustedSearchEngines::class);

        $this->assertSame([
            ['kind' => 'unsupported_search_engine', 'subject' => Document::class, 'position' => null, 'message' => UnsupportedSearchEngineException::untrusted(new Document, new MemoryEngine)->getMessage()],
        ], $this->problemsOf(Document::class));
    }

    #[Test]
    public function it_reports_a_perimeter_without_search_keys(): void
    {
        $this->assertSame([
            ['kind' => 'perimeter_without_search_keys', 'subject' => KeylessDocumentControl::class, 'position' => '#1', 'message' => 'The ['.OwnPerimeter::class.'] perimeter at #1 of the ['.KeylessDocumentControl::class.'] control declares no search keys, so a reader that only this perimeter reaches finds none of its ['.KeylessDocument::class.'] rows in a controlled text search. Declare searchKeys() on the perimeter, or unrestricted() if it reaches every row.'],
        ], $this->problemsOf(KeylessDocument::class));
    }

    #[Test]
    public function it_reports_each_position_of_a_perimeter_class_without_search_keys(): void
    {
        $this->assertSame([
            ['kind' => 'perimeter_without_search_keys', 'subject' => RepeatedDocumentControl::class, 'position' => '#1', 'message' => $this->keylessAt('#1')],
            ['kind' => 'perimeter_without_search_keys', 'subject' => RepeatedDocumentControl::class, 'position' => '#2.2', 'message' => $this->keylessAt('#2.2')],
        ], $this->problemsOf(RepeatedDocument::class));
    }

    #[Test]
    public function it_prints_the_position_of_the_perimeter_in_the_problem(): void
    {
        $code = Artisan::call('access:check', ['--only' => [RepeatedDocument::class]]);
        $output = Artisan::output();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('The ['.OwnPerimeter::class.'] perimeter at #1 of the ['.RepeatedDocumentControl::class.'] control declares no search keys', $output);
        $this->assertStringContainsString('The ['.OwnPerimeter::class.'] perimeter at #2.2 of the ['.RepeatedDocumentControl::class.'] control declares no search keys', $output);
        $this->assertStringContainsString('2 problems found.', $output);
    }

    #[Test]
    public function it_reports_a_perimeter_holding_another_one_for_its_own_search_keys(): void
    {
        $this->assertSame([
            ['kind' => 'perimeter_without_search_keys', 'subject' => WrappedDocumentControl::class, 'position' => '#1', 'message' => 'The ['.WrappingPerimeter::class.'] perimeter at #1 of the ['.WrappedDocumentControl::class.'] control declares no search keys, so a reader that only this perimeter reaches finds none of its ['.WrappedDocument::class.'] rows in a controlled text search. Declare searchKeys() on the perimeter, or unrestricted() if it reaches every row. The search keys of the perimeters it holds are never read: declare them on this perimeter itself.'],
        ], $this->problemsOf(WrappedDocument::class));
    }

    #[Test]
    public function it_reports_a_boundary_without_a_search_form(): void
    {
        $this->assertSame([
            ['kind' => 'unsearchable_boundary', 'subject' => BoundedDocumentControl::class, 'position' => null, 'message' => UnsearchableBoundaryException::for(Access::controlFor(BoundedDocument::class))->getMessage()],
        ], $this->problemsOf(BoundedDocument::class));
    }

    #[Test]
    public function it_reports_a_make_searchable_using_that_writes_no_access_keys(): void
    {
        $this->assertSame([
            ['kind' => 'search_freshness', 'subject' => RewrittenDocument::class, 'position' => null, 'message' => 'The ['.RewrittenDocument::class.'] model overrides makeSearchableUsing() and its body never calls withAccessKeys(), so its documents carry no access keys and no controlled search finds them. Return $this->withAccessKeys($models) from it.'],
        ], $this->problemsOf(RewrittenDocument::class));
    }

    #[Test]
    public function it_reports_a_relation_read_by_the_keys_that_nothing_reindexes(): void
    {
        $this->assertSame([
            ['kind' => 'search_freshness', 'subject' => RelatedDocument::class, 'position' => '#1', 'message' => 'The [client] search keys of the ['.RelatedDocument::class.'] model read the [folder] relation, but nothing reindexes the documents when ['.Folder::class.'] changes, so they keep their former keys until they are reindexed. Declare reindexWhen(Folder::class, ...) on the perimeter, use ReindexesSearch on the pivot, or call searchable() after the change.'],
        ], $this->problemsOf(RelatedDocument::class));
    }

    #[Test]
    public function it_reports_a_controlled_model_that_uses_scout_searchable(): void
    {
        $this->assertSame([
            ['kind' => 'plain_searchable', 'subject' => PlainDocument::class, 'position' => null, 'message' => MissingControlledSearchableException::for(new PlainDocument)->getMessage()],
        ], $this->problemsOf(PlainDocument::class));
    }

    #[Test]
    public function it_reports_a_relation_that_reaches_a_reindexing_pivot_without_using_it(): void
    {
        [$code, $report] = $this->checkJson(['--only' => [TaggedDocument::class], '--models' => [$this->fixture('Search/Pivot/Models')]]);

        $this->assertSame(1, $code);
        $this->assertSame(2, $report['pivots']['relations']);
        $this->assertSame([
            ['kind' => 'pivot_without_using', 'subject' => LooseTag::class, 'position' => null, 'message' => 'The [documents] relation of the ['.LooseTag::class.'] model reaches the [document_tag] table without ->using(DocumentTag::class), so attach(), detach() and sync() through it fire no pivot event and the documents that ['.DocumentTag::class.'] reindexes keep their former keys. Add ->using(DocumentTag::class) to the relation.'],
        ], $report['problems']);
    }

    private function keylessAt(string $position): string
    {
        return 'The ['.OwnPerimeter::class."] perimeter at {$position} of the [".RepeatedDocumentControl::class.'] control declares no search keys, so a reader that only this perimeter reaches finds none of its ['.RepeatedDocument::class.'] rows in a controlled text search. Declare searchKeys() on the perimeter, or unrestricted() if it reaches every row.';
    }

    /**
     * @param  class-string  $model
     * @return list<array{kind: string, subject: string, position: string|null, message: string}>
     */
    private function problemsOf(string $model): array
    {
        [$code, $report] = $this->checkJson(['--only' => [$model]]);

        $this->assertSame(1, $code);

        return $report['problems'];
    }
}
