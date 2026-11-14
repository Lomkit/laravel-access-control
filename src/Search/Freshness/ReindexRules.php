<?php

namespace Lomkit\Access\Search\Freshness;

use Lomkit\Access\Exceptions\DuplicateReindexException;
use Lomkit\Access\Perimeters\Perimeter;

/** @internal */
final readonly class ReindexRules
{
    /** @param  list<ReindexWhen>  $rules */
    private function __construct(private array $rules) {}

    public static function none(): self
    {
        return new self([]);
    }

    /** @throws DuplicateReindexException When a rule already reindexes for the same model, a parent or a child of it. */
    public function with(ReindexWhen $rule, Perimeter $perimeter): self
    {
        foreach ($this->rules as $declared) {
            if (is_a($rule->model(), $declared->model(), true) || is_a($declared->model(), $rule->model(), true)) {
                throw DuplicateReindexException::for($perimeter, $rule->model(), $declared->model());
            }
        }

        return new self([...$this->rules, $rule]);
    }

    /** @return list<ReindexWhen> */
    public function all(): array
    {
        return $this->rules;
    }

    public function for(string $model): ?ReindexWhen
    {
        foreach ($this->rules as $rule) {
            if ($rule->model() === $model) {
                return $rule;
            }
        }

        return null;
    }

    public function matches(self $other): bool
    {
        if (count($this->rules) !== count($other->rules)) {
            return false;
        }

        foreach ($this->rules as $index => $rule) {
            if (! $rule->matches($other->rules[$index])) {
                return false;
            }
        }

        return true;
    }
}
