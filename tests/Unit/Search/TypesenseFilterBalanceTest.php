<?php

namespace Lomkit\Access\Tests\Unit\Search;

use Lomkit\Access\Search\TypesenseFilterBalance;
use Lomkit\Access\Search\TypesenseFilterTokenizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TypesenseFilterBalanceTest extends TestCase
{
    private const string ACCESS = '_boundary:=[`team.1`] && _access:=[`client.7`]';

    /** @return array<string, array{string, list<string>|null}> */
    public static function tokenizedFilters(): array
    {
        return [
            'groups and operators' => ['(a:=1 || b:=2) && c:=3', ['(', 'a:=1', '||', 'b:=2', ')', '&&', 'c:=3']],
            'operators and parentheses in a quoted value' => ['b:=`x) && (y`', ['b:=`x) && (y`']],
            'a backtick that starts a token' => ['`a`', ['`a`']],
            'a backtick that starts a token before a parenthesis' => ['`)a`', ['`', ')', 'a`']],
            'a geo filter' => ['loc:(1, 2, 3 km) && a:=1', ['loc:(1, 2, 3 km)', '&&', 'a:=1']],
            'a geo filter after a space' => ['loc: (1, 2, 3 km)', ['loc: (1, 2, 3 km)']],
            'a list of geo filters' => ['loc:[(1, 2, 3 km), (4, 5, 6 km)] || a:=1', ['loc:[(1, 2, 3 km), (4, 5, 6 km)]', '||', 'a:=1']],
            'a reference' => ['$C(a:=1 && (b:=2)) || c:=3', ['$C(a:=1 && (b:=2))', '||', 'c:=3']],
            'a negated reference' => ['!$C(a:=1)', ['!$C(a:=1)']],
            'an object filter' => ['o.{a:=1 && b:=2} && c:=3', ['o.{a:=1 && b:=2}', '&&', 'c:=3']],
            'an ampersand that starts a token' => ['a:=1 && & b:=2', null],
            'a pipe that starts a token' => ['| a:=1', null],
            'a reference without parenthesis' => ['$Cx', null],
            'an open reference' => ['$C(a:=1', null],
            'an open object filter' => ['o.{a:=1', null],
            'a nested object filter' => ['o.{a.{b:=1}}', null],
            'an open list of geo filters' => ['loc:[(1, 2, 3 km)', null],
        ];
    }

    /** @param  list<string>|null  $tokens */
    #[Test]
    #[DataProvider('tokenizedFilters')]
    public function it_tokenizes_a_filter_as_typesense_does(string $filter, ?array $tokens): void
    {
        $this->assertSame($tokens, TypesenseFilterTokenizer::tokenize($filter));
    }

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
            'a list of geo filters' => ['location:[(48.85, 2.34, 5 km), (45.76, 4.83, 2 km)]'],
            'a reference with a quoted value' => ['$Authors(name:=`Ada (Jr)`)'],
            'a negated reference' => ['!$Authors(id:=1)'],
            'an object filter with a quoted value' => ['address.{city:=`Paris`}'],
            'a backslash before a backtick' => ['x:=`a\` || y:=1'],
            'parentheses that Typesense refuses as unbalanced' => ['(featured:=true'],
            'a group closed and opened again' => ['status:=published) || (status:=draft'],
        ];
    }

    #[Test]
    #[DataProvider('selfContainedFilters')]
    public function it_accepts_a_filter_that_ends_on_its_own(string $filter): void
    {
        $this->assertTrue(TypesenseFilterBalance::isSelfContained($filter));
    }

    /** @return array<string, array{string}> */
    public static function filtersThatDoNotEndOnTheirOwn(): array
    {
        return [
            'a quoted value left open' => ['title:=`Scout'],
            'a quoted value left open after a group' => ['id:*) || title:=`z'],
            'a backtick that starts a token' => ['`)a`'],
            'an empty pair of backticks' => ['``'],
            'a quoted value inside an object filter' => ['o.{a:=`} ) && x:=`b'],
            'a quoted value inside a reference' => ['$C(k:=`v) && y:=`w)'],
            'a geo filter inside a quoted value' => ['title:=`a:(b`'],
            'a geo filter left open' => ['loc:(1, 2, 3 km'],
            'a reference left open' => ['$Authors(id:=1'],
            'an object filter left open' => ['address.{city:=Paris'],
            'a list of geo filters left open' => ['location:[(48.85, 2.34, 5 km)'],
            'an ampersand that starts a token' => ['& a:=1'],
        ];
    }

    #[Test]
    #[DataProvider('filtersThatDoNotEndOnTheirOwn')]
    public function it_refuses_a_filter_that_does_not_end_on_its_own(string $filter): void
    {
        $this->assertFalse(TypesenseFilterBalance::isSelfContained($filter));
    }

    #[Test]
    public function it_finds_the_group_at_the_end_of_a_combined_filter(): void
    {
        $this->assertTrue(TypesenseFilterBalance::endsWithGroup('(featured:=true) && ('.self::ACCESS.')', self::ACCESS));
        $this->assertTrue(TypesenseFilterBalance::endsWithGroup('(a:=1) || (b:=2) && ('.self::ACCESS.')', self::ACCESS));
        $this->assertFalse(TypesenseFilterBalance::endsWithGroup('(title:=`z) && ('.self::ACCESS.')', self::ACCESS));
        $this->assertTrue(TypesenseFilterBalance::endsWithGroup('((featured:=true) && ('.self::ACCESS.')', self::ACCESS));
        $this->assertFalse(TypesenseFilterBalance::endsWithGroup('(featured:=true) || ('.self::ACCESS.')', self::ACCESS));
        $this->assertFalse(TypesenseFilterBalance::endsWithGroup('(& b) && ('.self::ACCESS.')', self::ACCESS));
    }

    #[Test]
    public function it_keeps_the_group_at_the_end_for_every_filter_it_accepts(): void
    {
        $fragments = ['(', ')', '`', '$C(', '!$C(', 'o.{', '}', 'a:(', 'b: (', 'c:[', '[', ']', ' && ', ' || ', 'x:=1', 'y:=`v', ',', '.', ':', ' ', '&', '|'];
        mt_srand(96);

        for ($sample = 0; $sample < 20000; $sample++) {
            $filter = '';

            for ($length = mt_rand(1, 8); $length > 0; $length--) {
                $filter .= $fragments[mt_rand(0, count($fragments) - 1)];
            }

            $tokens = TypesenseFilterTokenizer::tokenize("({$filter}) && (".self::ACCESS.')');

            if (! TypesenseFilterBalance::isSelfContained($filter) || $tokens === null || ! self::isBalanced($tokens)) {
                continue;
            }

            $this->assertTrue(TypesenseFilterBalance::endsWithGroup("({$filter}) && (".self::ACCESS.')', self::ACCESS), $filter);
        }
    }

    /** @param  list<string>  $tokens */
    private static function isBalanced(array $tokens): bool
    {
        $depth = 0;

        foreach ($tokens as $token) {
            $depth += match ($token) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }
}
