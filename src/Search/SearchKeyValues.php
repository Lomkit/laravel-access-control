<?php

namespace Lomkit\Access\Search;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Access\Exceptions\InvalidSearchKeyValueException;

final class SearchKeyValues
{
    private const string PLAIN_COMPONENT = '/^[a-z0-9][a-z0-9_]*$/';

    private const string ESCAPE = '_';

    private const string SEPARATOR = '.';

    /**
     * @return list<string>
     *
     * @throws InvalidSearchKeyValueException When a value is not an int, a string, a backed enum, null, a list or a tuple of them.
     */
    public static function keys(string $name, mixed $values, Model $source): array
    {
        $keys = [];

        foreach (is_array($values) ? $values : [$values] as $value) {
            $key = self::key($name, $value, $source);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    public static function component(int|string $value): string
    {
        $value = (string) $value;

        return preg_match(self::PLAIN_COMPONENT, $value) === 1 ? $value : self::ESCAPE.bin2hex($value);
    }

    private static function key(string $name, mixed $value, Model $source): ?string
    {
        $components = is_array($value) ? array_values($value) : [$value];

        if ($components === []) {
            throw InvalidSearchKeyValueException::emptyTuple($name, $source);
        }

        $key = $name;

        foreach ($components as $component) {
            if ($component === null) {
                return null;
            }

            $key .= self::SEPARATOR.self::component(self::scalar($name, $component, $source));
        }

        return $key;
    }

    private static function scalar(string $name, mixed $component, Model $source): int|string
    {
        if ($component instanceof BackedEnum) {
            return $component->value;
        }

        if (is_int($component) || is_string($component)) {
            return $component;
        }

        throw InvalidSearchKeyValueException::for($name, $source, $component);
    }
}
