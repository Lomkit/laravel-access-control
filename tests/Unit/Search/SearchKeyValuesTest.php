<?php

namespace Lomkit\Access\Tests\Unit\Search;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\InvalidSearchKeyValueException;
use Lomkit\Access\Search\SearchKeyValues;
use Lomkit\Access\Tests\Fixtures\Blog\Region;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class SearchKeyValuesTest extends TestCase
{
    protected function source(): Model
    {
        return new class extends Model {};
    }

    /** @return array<string, array{mixed, list<string>}> */
    public static function values(): array
    {
        return [
            'an int' => [7, ['client.7']],
            'a numeric string, as an int' => ['7', ['client.7']],
            'a lowercase string' => ['fr', ['client.fr']],
            'a string with underscores' => ['north_east', ['client.north_east']],
            'an uppercase string, escaped' => ['FR', ['client._4652']],
            'a dotted string, escaped' => ['a.7', ['client._612e37']],
            'a string that starts with the escape' => ['_x', ['client._5f78']],
            'an empty string' => ['', ['client._']],
            'a negative int' => [-7, ['client._2d37']],
            'a backed enum' => [Region::NorthEast, ['client.north_east']],
            'a backed enum with an uppercase value' => [Region::Paris, ['client._5061726973']],
            'null' => [null, []],
            'a list' => [[3, 7, 3, null], ['client.3', 'client.7']],
            'a tuple' => [[['fr', 7]], ['client.fr.7']],
            'a tuple with a null part' => [[['fr', null], ['de', 7]], ['client.de.7']],
            'a list of values and tuples' => [[3, ['fr', 7]], ['client.3', 'client.fr.7']],
            'an empty list' => [[], []],
        ];
    }

    /** @param  list<string>  $keys */
    #[Test]
    #[DataProvider('values')]
    public function it_turns_each_value_into_a_key_prefixed_with_the_name(mixed $value, array $keys): void
    {
        $this->assertSame($keys, SearchKeyValues::keys('client', $value, $this->source()));
    }

    #[Test]
    public function it_never_gives_the_same_key_to_a_tuple_and_a_dotted_value(): void
    {
        $this->assertNotSame(
            SearchKeyValues::keys('client', 'a.7', $this->source()),
            SearchKeyValues::keys('client', [['a', 7]], $this->source()),
        );
    }

    /** @return array<string, array{mixed, string}> */
    public static function invalidValues(): array
    {
        return [
            'a float' => [1.5, 'float'],
            'a boolean' => [true, 'bool'],
            'an object' => [new stdClass, 'stdClass'],
            'a nested tuple' => [[[['fr'], 7]], 'array'],
        ];
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function it_throws_on_a_value_that_is_not_a_key(mixed $value, string $type): void
    {
        $this->expectException(InvalidSearchKeyValueException::class);
        $this->expectExceptionMessage("The [client] search keys received a value of type [{$type}]");

        SearchKeyValues::keys('client', $value, $this->source());
    }

    #[Test]
    public function it_throws_on_an_empty_tuple(): void
    {
        $this->expectException(InvalidSearchKeyValueException::class);
        $this->expectExceptionMessage('The [client] search keys received an empty tuple');

        SearchKeyValues::keys('client', [[]], $this->source());
    }
}
