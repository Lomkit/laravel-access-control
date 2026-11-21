<?php

namespace Lomkit\Access\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Lomkit\Access\Search\Parents\ParentKey;
use Lomkit\Access\Search\SearchDocuments;
use Lomkit\Access\Search\SearchRules;

/** @internal */
final readonly class ClockIndependence
{
    public const int DAYS_AWAY = 400;

    private const string SEPARATOR = '.';

    /** @param  array<int|string, Model>  $rows */
    public function __construct(
        private SearchRules $rules,
        private array $rows,
    ) {}

    /** @return array{row: string, field: string, rules: list<string>, days: int}|null */
    public function drift(): ?array
    {
        $previous = Date::getTestNow();
        $now = Date::now();

        try {
            Date::setTestNow($now);
            $current = $this->documents();

            foreach ([-self::DAYS_AWAY, self::DAYS_AWAY] as $days) {
                Date::setTestNow($now->copy()->addDays($days));
                $drift = $this->driftBetween($current, $this->documents(), $days);

                if ($drift !== null) {
                    return $drift;
                }
            }

            return null;
        } finally {
            Date::setTestNow($previous);
        }
    }

    /** @return array<int|string, array<string, list<string>>> */
    private function documents(): array
    {
        return array_combine(array_keys($this->rows), (new SearchDocuments($this->rules))->readableKeysFor(array_values($this->rows)));
    }

    /**
     * @param  array<int|string, array<string, list<string>>>  $current
     * @param  array<int|string, array<string, list<string>>>  $moved
     * @return array{row: string, field: string, rules: list<string>, days: int}|null
     */
    private function driftBetween(array $current, array $moved, int $days): ?array
    {
        foreach ($current as $row => $fields) {
            foreach ($fields as $field => $keys) {
                $movedKeys = $moved[$row][$field] ?? [];
                $changed = [...array_diff($keys, $movedKeys), ...array_diff($movedKeys, $keys)];

                if ($changed !== []) {
                    return ['row' => (string) $row, 'field' => $field, 'rules' => self::rulesOf($changed), 'days' => $days];
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function rulesOf(array $keys): array
    {
        $rules = [];

        foreach ($keys as $key) {
            $components = explode(self::SEPARATOR, $key);
            $rules[] = $components[0] === ParentKey::PREFIX && isset($components[1])
                ? $components[0].self::SEPARATOR.$components[1]
                : $components[0];
        }

        $rules = array_values(array_unique($rules));
        sort($rules);

        return $rules;
    }
}
