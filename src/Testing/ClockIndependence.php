<?php

namespace Lomkit\Access\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Lomkit\Access\Search\SearchDocuments;
use Lomkit\Access\Search\SearchRules;

/** @internal */
final readonly class ClockIndependence
{
    private const int DAYS_AWAY = 400;

    /** @param  array<int|string, Model>  $rows */
    public function __construct(
        private SearchRules $rules,
        private array $rows,
    ) {}

    public function drift(): ?ClockDrift
    {
        $previous = Carbon::getTestNow();
        $now = Carbon::now();

        try {
            Carbon::setTestNow($now);
            $current = $this->documents();

            foreach ([-self::DAYS_AWAY, self::DAYS_AWAY] as $days) {
                Carbon::setTestNow($now->copy()->addDays($days));
                $drift = $this->driftBetween($current, $this->documents(), $days);

                if ($drift !== null) {
                    return $drift;
                }
            }

            return null;
        } finally {
            Carbon::setTestNow($previous);
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
     */
    private function driftBetween(array $current, array $moved, int $days): ?ClockDrift
    {
        foreach ($current as $row => $fields) {
            foreach ($fields as $field => $keys) {
                $movedKeys = $moved[$row][$field] ?? [];
                $changed = [...array_diff($keys, $movedKeys), ...array_diff($movedKeys, $keys)];

                if ($changed !== []) {
                    return ClockDrift::of($this->rules, (string) $row, $field, $changed, $days);
                }
            }
        }

        return null;
    }
}
