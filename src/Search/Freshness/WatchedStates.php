<?php

namespace Lomkit\Access\Search\Freshness;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class WatchedStates
{
    private function __construct(private string $encoded) {}

    /** @param  list<array<string, mixed>>  $states */
    public static function of(array $states): self
    {
        return new self(base64_encode(serialize($states)));
    }

    /** @return list<Model> */
    public function instancesOf(Model $prototype): array
    {
        $instances = [];

        foreach ($this->decoded() as $attributes) {
            $instances[] = $prototype->newFromBuilder($attributes);
        }

        return $instances;
    }

    public function identity(): string
    {
        return $this->encoded;
    }

    /** @return list<array<string, mixed>> */
    private function decoded(): array
    {
        $states = unserialize((string) base64_decode($this->encoded, true), ['allowed_classes' => false]);
        $decoded = [];

        foreach (is_array($states) ? $states : [] as $state) {
            if (is_array($state)) {
                $decoded[] = array_filter($state, is_string(...), ARRAY_FILTER_USE_KEY);
            }
        }

        return $decoded;
    }
}
