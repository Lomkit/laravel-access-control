<?php

namespace Lomkit\Access\Search\Guard;

/** @internal */
final readonly class AlgoliaFilter
{
    private const string ESCAPE = '\\';

    private const array READINGS = ['"\'', '"'];

    private const string DANGLING_OPERATOR = '/^\s*(AND|OR)(?![A-Za-z0-9_])|(?<![A-Za-z0-9_])(AND|OR|NOT)\s*$/i';

    private function __construct(private string $expression, private bool $disjunctive) {}

    public static function of(string $filter): ?self
    {
        if (str_contains($filter, self::ESCAPE) || preg_match(self::DANGLING_OPERATOR, $filter) === 1) {
            return null;
        }

        $disjunctive = false;

        foreach (self::READINGS as $quotes) {
            $read = self::read($filter, $quotes);

            if ($read === null) {
                return null;
            }

            $disjunctive = $disjunctive || $read;
        }

        return new self($filter, $disjunctive);
    }

    public static function escapes(string $filter): bool
    {
        return str_contains($filter, self::ESCAPE);
    }

    public function and(string $access): string
    {
        if (trim($this->expression) === '') {
            return $access;
        }

        return $this->disjunctive ? "({$this->expression}) AND {$access}" : "{$this->expression} AND {$access}";
    }

    private static function read(string $filter, string $quotes): ?bool
    {
        $depth = 0;
        $quote = null;
        $disjunctive = false;
        $length = strlen($filter);

        for ($position = 0; $position < $length; $position++) {
            $character = $filter[$position];

            if ($quote !== null) {
                $quote = $character === $quote ? null : $quote;

                continue;
            }

            $quote = str_contains($quotes, $character) ? $character : null;
            $depth += match ($character) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth < 0) {
                return null;
            }

            $disjunctive = $disjunctive || ($depth === 0 && self::startsOr($filter, $position));
        }

        return $quote === null && $depth === 0 ? $disjunctive : null;
    }

    private static function startsOr(string $filter, int $position): bool
    {
        return strcasecmp(substr($filter, $position, 2), 'or') === 0
            && ! self::isWordCharacter($filter[$position - 1] ?? ' ')
            && ! self::isWordCharacter($filter[$position + 2] ?? ' ');
    }

    private static function isWordCharacter(string $character): bool
    {
        return ctype_alnum($character) || $character === '_';
    }
}
