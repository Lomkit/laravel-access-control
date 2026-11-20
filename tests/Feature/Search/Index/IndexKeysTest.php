<?php

namespace Lomkit\Access\Tests\Feature\Search\Index;

use Illuminate\Database\Eloquent\Relations\Relation;
use Lomkit\Access\Access;
use Lomkit\Access\Search\SearchDocuments;
use Lomkit\Access\Search\SearchKeyDigest;
use Lomkit\Access\Tests\Fixtures\Blog\User;
use Lomkit\Access\Tests\Fixtures\Index\Guide;
use Lomkit\Access\Tests\Fixtures\Index\GuideControl;
use Lomkit\Access\Tests\Fixtures\Index\MislabeledTicketControl;
use Lomkit\Access\Tests\Fixtures\Index\Ticket;
use Lomkit\Access\Tests\Fixtures\Index\TicketControl;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;

class IndexKeysTest extends IndexKeysTestCase
{
    #[Test]
    public function it_agrees_with_the_controlled_list_for_every_reader(): void
    {
        $this->assertIndexKeysAgree(GuideControl::class, $this->readers());
        $this->assertIndexKeysAgree(TicketControl::class, $this->readers());

        $this->brand = 2;

        $this->assertIndexKeysAgree(GuideControl::class, $this->readers());
    }

    #[Test]
    public function it_finds_with_a_naive_filter_on_both_fields_the_rows_of_the_controlled_list(): void
    {
        $collection = $this->collection();

        foreach ($this->readers() as $reader) {
            foreach ([Guide::class, Ticket::class] as $model) {
                $this->assertSame($this->listed($model, $reader), $this->naivelyFound(Access::indexReaderKeys($reader, $model), $collection));
            }
        }

        $this->assertSame(['Guide Billing', 'Guide Onboarding'], $this->naivelyFound(Access::indexReaderKeys($this->root, Guide::class), $collection));
        $this->assertSame(['Guide Onboarding'], $this->naivelyFound(Access::indexReaderKeys($this->ada, Guide::class), $collection));
        $this->assertSame(['Guide Onboarding'], $this->naivelyFound(Access::indexReaderKeys($this->grace, Guide::class), $collection));
        $this->assertSame(['Ticket Login fails', 'Ticket Lost invoice', 'Ticket Slow export'], $this->naivelyFound(Access::indexReaderKeys($this->root, Ticket::class), $collection));
        $this->assertSame(['Ticket Login fails'], $this->naivelyFound(Access::indexReaderKeys($this->ada, Ticket::class), $collection));
        $this->assertSame(['Ticket Login fails'], $this->naivelyFound(Access::indexReaderKeys($this->linus, Ticket::class), $collection));
    }

    #[Test]
    public function it_never_finds_a_document_of_another_model_with_the_same_rule_names(): void
    {
        $collection = $this->collection();

        foreach ($this->readers() as $reader) {
            $this->assertSame([], array_filter($this->naivelyFound(Access::indexReaderKeys($reader, Guide::class), $collection), fn (string $name): bool => str_starts_with($name, 'Ticket ')));
            $this->assertSame([], array_filter($this->naivelyFound(Access::indexReaderKeys($reader, Ticket::class), $collection), fn (string $name): bool => str_starts_with($name, 'Guide ')));
        }

        $this->assertSame(['_access', '_boundary'], array_keys(Access::indexReaderKeys($this->root, Ticket::class)->values()));
        $this->assertSame([], array_intersect(Access::indexReaderKeys($this->root, Ticket::class)->values()['_access'], Access::indexKeysOf(Guide::query()->get())[$this->guideKey('Onboarding')]['_access']));
    }

    #[Test]
    public function it_closes_a_reader_with_a_value_that_no_document_carries(): void
    {
        $collection = $this->collection();
        $this->brand = null;

        foreach ([[Guide::class, null], [Guide::class, $this->nobody], [Guide::class, $this->linus], [Guide::class, $this->root], [Ticket::class, null], [Ticket::class, $this->nobody]] as [$model, $reader]) {
            $keys = Access::indexReaderKeys($reader, $model);

            $this->assertTrue($keys->isClosed());
            $this->assertSame(['_access' => ['#closed'], '_boundary' => ['#closed']], $keys->values());
            $this->assertSame([], $this->naivelyFound($keys, $collection));
        }
    }

    #[Test]
    public function it_constrains_both_fields_of_an_open_reader(): void
    {
        foreach ([[Guide::class, $this->root], [Guide::class, $this->ada], [Ticket::class, $this->root], [Ticket::class, $this->grace]] as [$model, $reader]) {
            $keys = Access::indexReaderKeys($reader, $model);

            $this->assertFalse($keys->isClosed());
            $this->assertSame($model, $keys->model());
            $this->assertSame(['_access', '_boundary'], array_keys($keys->values()));
            $this->assertNotSame([], $keys->values()['_access']);
            $this->assertNotSame([], $keys->values()['_boundary']);
        }
    }

    #[Test]
    public function it_gives_digests_only(): void
    {
        $values = [];

        foreach ($this->collection() as $document) {
            array_push($values, ...$document['_access'], ...$document['_boundary']);
        }

        foreach ($this->readers() as $reader) {
            foreach ([Guide::class, Ticket::class] as $model) {
                $keys = Access::indexReaderKeys($reader, $model);

                if (! $keys->isClosed()) {
                    array_push($values, ...$keys->values()['_access'], ...$keys->values()['_boundary']);
                }
            }
        }

        $this->assertNotSame([], $values);

        foreach ($values as $value) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $value);
        }
    }

    #[Test]
    public function it_never_gives_the_values_of_a_scout_document_for_the_same_keys(): void
    {
        $rows = Guide::query()->orderBy('id')->get();
        $readable = (new SearchDocuments(Access::controlFor(Guide::class)->searchRules()))->readableKeysFor(array_values($rows->all()));
        $documents = array_values(Access::indexKeysOf($rows));

        $this->assertSame(['author.'.$this->ada->getKey(), 'client.'.$this->acme->getKey()], $readable[0]['_access']);

        foreach ($readable as $index => $keys) {
            foreach (['_access', '_boundary'] as $field) {
                $this->assertSame([], array_intersect(SearchKeyDigest::all($keys[$field]), $documents[$index][$field]));
            }
        }

        $filter = Access::controlFor(Guide::class)->searchFilter($this->ada);
        $this->assertSame([], array_intersect($filter->whereIns()['_access'], Access::indexReaderKeys($this->ada, Guide::class)->values()['_access']));
    }

    #[Test]
    public function it_digests_each_key_with_the_morph_class_of_its_model(): void
    {
        $morph = '_'.bin2hex(Guide::class);
        $onboarding = Access::indexKeysOf(Guide::query()->where('title', 'Onboarding')->get());
        $document = reset($onboarding);

        $this->assertSame([
            '_access' => [
                SearchKeyDigest::of("@{$morph}.author.{$this->ada->getKey()}"),
                SearchKeyDigest::of("@{$morph}.client.{$this->acme->getKey()}"),
                SearchKeyDigest::of("@{$morph}.*"),
            ],
            '_boundary' => [SearchKeyDigest::of("@{$morph}.brand.1")],
        ], $document);
        $this->assertSame(['_access' => [SearchKeyDigest::of("@{$morph}.*")], '_boundary' => [SearchKeyDigest::of("@{$morph}.brand.1")]], Access::indexReaderKeys($this->root, Guide::class)->values());

        Relation::morphMap(['guide' => Guide::class, 'ticket' => Ticket::class]);

        $this->assertSame(['_access' => [SearchKeyDigest::of('@guide.*')], '_boundary' => [SearchKeyDigest::of('@guide.brand.1')]], Access::indexReaderKeys($this->root, Guide::class)->values());
        $this->assertSame(['_access' => [SearchKeyDigest::of('@ticket.*')], '_boundary' => [SearchKeyDigest::of('@ticket.*')]], Access::indexReaderKeys($this->root, Ticket::class)->values());
        $this->assertSame([], array_intersect($document['_access'], Access::indexKeysOf(Guide::query()->where('title', 'Onboarding')->get())[$this->guideKey('Onboarding')]['_access']));
    }

    #[Test]
    public function it_leaves_the_boundary_of_a_row_without_a_boundary_value_empty(): void
    {
        $orphan = Access::indexKeysOf(Guide::query()->where('title', 'Orphan')->get());
        $document = reset($orphan);

        $this->assertIsArray($document);
        $this->assertSame([], $document['_boundary']);
        $this->assertSame([SearchKeyDigest::of('@_'.bin2hex(Guide::class).'.*')], $document['_access']);
        $this->assertNotContains('Guide Orphan', $this->naivelyFound(Access::indexReaderKeys($this->root, Guide::class), $this->collection()));
    }

    #[Test]
    public function it_gives_the_documents_by_the_key_of_each_row(): void
    {
        $guides = Guide::query()->orderBy('id')->get();

        $this->assertSame($guides->modelKeys(), array_keys(Access::indexKeysOf($guides)));
        $this->assertSame([], Access::indexKeysOf([]));
        $this->assertSame([], Access::indexKeysOf(Guide::query()->whereKey(0)->get()));
    }

    #[Test]
    public function it_follows_the_decision_context_of_the_request(): void
    {
        $collection = $this->collection();

        $this->brand = 2;

        $this->assertSame(['Guide Pricing'], $this->naivelyFound(Access::indexReaderKeys($this->root, Guide::class), $collection));
        $this->assertSame(['Guide Pricing'], $this->naivelyFound(Access::indexReaderKeys($this->grace, Guide::class), $collection));
        $this->assertFalse(Access::indexReaderKeys($this->ada, Guide::class)->isClosed());
        $this->assertSame([], $this->naivelyFound(Access::indexReaderKeys($this->ada, Guide::class), $collection));
    }

    #[Test]
    public function it_reports_the_rows_on_which_the_keys_disagree_with_the_sql(): void
    {
        try {
            $this->assertIndexKeysAgree(MislabeledTicketControl::class, [$this->nobody, $this->ada]);
        } catch (AssertionFailedError $failure) {
            $this->assertStringContainsString(sprintf('[%s] control do not agree with its SQL for the [%s] reader with key [%s]', MislabeledTicketControl::class, User::class, $this->ada->getKey()), $failure->getMessage());
            $this->assertStringContainsString(sprintf('finds the rows with keys [%s] that the SQL does not, and misses the rows with keys [%s] that the SQL finds', $this->ticketKey('Slow export'), $this->ticketKey('Login fails')), $failure->getMessage());

            return;
        }

        $this->fail('assertIndexKeysAgree() accepted keys that disagree with the SQL.');
    }

    #[Test]
    public function it_needs_a_reader_to_compare(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('assertIndexKeysAgree() received no reader');

        $this->assertIndexKeysAgree(GuideControl::class, []);
    }
}
