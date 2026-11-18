<?php

namespace Lomkit\Access\Tests\Unit\Search;

use Lomkit\Access\Search\TypesenseFilterBalance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TypesenseFilterBalanceTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function selfContainedFilters(): array
    {
        return [
            'an empty filter' => [''],
            'a plain filter' => ['featured:=true'],
            'nested groups' => ['(featured:=true || author_id:=[1, 2]) && (title:=`x` || (author_id:>3))'],
            'a parenthesis in a quoted value' => ['title:=`Scout (one`'],
            'parentheses in quoted values in any order' => ['title:=`a)b` || title:=`(c`'],
            'operators in a quoted value' => ['title:=`a && b || c`'],
            'a geo filter' => ['location:(48.85, 2.34, 5 km)'],
        ];
    }

    #[Test]
    #[DataProvider('selfContainedFilters')]
    public function it_accepts_a_self_contained_filter(string $filter): void
    {
        $this->assertTrue(TypesenseFilterBalance::isSelfContained($filter));
    }

    /** @return array<string, array{string}> */
    public static function filtersThatAreNotSelfContained(): array
    {
        return [
            'a parenthesis closed before it opens' => ['status:=published) || (status:=draft'],
            'a backtick left open after a closing parenthesis' => ['id:*) || title:=`z'],
            'a backtick left open' => ['title:=`Scout'],
            'an opening parenthesis left open' => ['(featured:=true'],
            'a closing parenthesis too many' => ['featured:=true)'],
            'parentheses in the wrong order' => [')featured:=true('],
            'a quoted parenthesis that leaves one open' => ['(title:=`)`'],
        ];
    }

    #[Test]
    #[DataProvider('filtersThatAreNotSelfContained')]
    public function it_refuses_a_filter_that_is_not_self_contained(string $filter): void
    {
        $this->assertFalse(TypesenseFilterBalance::isSelfContained($filter));
    }
}
