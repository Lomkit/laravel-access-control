<?php

namespace Lomkit\Access\Testing;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Access;
use Lomkit\Access\Controls\Control;
use Lomkit\Access\Exceptions\UnsearchableAbilityException;
use Lomkit\Access\Search\ControlledSearchable;
use PHPUnit\Framework\Assert;

trait AssertsSearchAgreement
{
    /**
     * @param  class-string<Control>  $control
     * @param  list<Model>  $readers
     */
    public function assertSearchAgrees(string $control, array $readers): void
    {
        if (func_num_args() > 2) {
            throw UnsearchableAbilityException::agreement($control);
        }

        $instance = Access::control($control);
        $model = $instance->getModel();

        Assert::assertContains(ControlledSearchable::class, class_uses_recursive($model), sprintf('The [%s] model does not use the ControlledSearchable trait, so its documents carry no access keys.', $model));
        Assert::assertTrue($instance->searchRules()->isSearchable(), sprintf('The boundary of the [%s] control has no search form, so a controlled search cannot keep the rows inside it.', $control));
        Assert::assertNotSame([], $readers, sprintf('assertSearchAgrees() received no reader for the [%s] control: the search agrees with the SQL only for the readers it compares.', $control));

        $agreement = new SearchAgreement($instance);
        $agreed = ['searchOnly' => [], 'sqlOnly' => []];

        foreach ($readers as $reader) {
            $disagreements = $agreement->disagreementsFor($reader);
            $failure = $disagreements === $agreed ? '' : $this->searchAgreesFailure($control, $reader, $agreement->readerKeys($reader), $disagreements);

            Assert::assertSame($agreed, $disagreements, $failure);
        }
    }

    /**
     * @param  class-string<Control>  $control
     * @param  array{searchOnly: list<string>, sqlOnly: list<string>}  $disagreements
     */
    private function searchAgreesFailure(string $control, Model $reader, string $readerKeys, array $disagreements): string
    {
        $key = $reader->getKey();

        return sprintf(
            'The search keys of the [%s] control do not agree with its SQL for the [%s] reader with key [%s], whose search holds [%s]: the keys find the rows [%s] that the SQL does not, and miss the rows [%s] that the SQL finds. Make the searchKeys() of each perimeter, and of the boundary, describe the same rows as its query().',
            $control,
            $reader::class,
            is_int($key) || is_string($key) ? $key : get_debug_type($key),
            $readerKeys,
            implode('; ', $disagreements['searchOnly']),
            implode('; ', $disagreements['sqlOnly']),
        );
    }

    /**
     * @param  class-string<Control>  $control
     * @param  list<Model|null>  $readers
     */
    public function assertIndexKeysAgree(string $control, array $readers): void
    {
        if (func_num_args() > 2) {
            throw UnsearchableAbilityException::indexAgreement($control);
        }

        Assert::assertNotSame([], $readers, sprintf('assertIndexKeysAgree() received no reader for the [%s] control: the keys of an index agree with the SQL only for the readers it compares.', $control));

        $agreement = new IndexKeysAgreement(Access::control($control));
        $agreed = ['indexOnly' => [], 'sqlOnly' => []];

        foreach ($readers as $reader) {
            $disagreements = $agreement->disagreementsFor($reader);

            Assert::assertSame($agreed, $disagreements, $disagreements === $agreed ? '' : $this->indexKeysAgreeFailure($control, $reader, $disagreements));
        }
    }

    /**
     * @param  class-string<Control>  $control
     * @param  array{indexOnly: list<string>, sqlOnly: list<string>}  $disagreements
     */
    private function indexKeysAgreeFailure(string $control, ?Model $reader, array $disagreements): string
    {
        $key = $reader?->getKey();

        return sprintf(
            'The keys that Access::searchKeysOf() and Access::readerKeysFor() give for the [%s] control do not agree with its SQL for %s: an index that filters on them finds the rows with keys [%s] that the SQL does not, and misses the rows with keys [%s] that the SQL finds, the first ten of each. Make the searchKeys() of each perimeter, and of the boundary, describe the same rows as its query().',
            $control,
            $reader === null ? 'no reader' : sprintf('the [%s] reader with key [%s]', $reader::class, is_int($key) || is_string($key) ? $key : get_debug_type($key)),
            implode(', ', $disagreements['indexOnly']),
            implode(', ', $disagreements['sqlOnly']),
        );
    }
}
